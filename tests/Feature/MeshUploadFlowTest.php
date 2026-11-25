<?php

namespace Tests\Feature;

use App\Contracts\Mesh\ManifestRepository;
use App\Contracts\Mesh\NodeRepository;
use App\Models\MeshTombstone;
use App\Models\User;
use App\Services\MeshTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeshUploadFlowTest extends TestCase
{
    private string $chunkRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chunkRoot = sys_get_temp_dir() . '/erugo-mesh-' . uniqid('', true);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'mesh.enabled' => true,
            'mesh.secret' => str_repeat('s', 64),
            'mesh.node_id' => 'coordinator',
            'mesh.nodes' => [
                'peer1' => 'http://peer1:80',
                'peer2' => 'http://peer2:80',
            ],
            'mesh.coordinator_url' => 'http://coordinator.test',
            'mesh.chunk_root' => $this->chunkRoot,
            'mesh.chunk_size' => 4,
            'mesh.write_copies' => 2,
            'mesh.max_upload_bytes' => 1024 * 1024,
            'mesh.max_chunks' => 100,
        ]);

        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->run();
        Http::fake();

        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->chunkRoot);
        parent::tearDown();
    }

    private function hashes(int $count, string $seed = 'a'): array
    {
        return array_map(
            fn (int $i) => str_repeat(chr(ord($seed) + $i), 64),
            range(0, $count - 1)
        );
    }

    private function createIntent(int $size = 8, ?array $hashes = null): array
    {
        $hashes ??= $this->hashes((int) ceil($size / 4));

        $response = $this->postJson('/api/mesh/uploads', [
            'filename' => 'clip.mp4',
            'size' => $size,
            'total_hash' => str_repeat('f', 64),
            'chunk_hashes' => $hashes,
        ]);

        $response->assertCreated();

        return $response->json('data');
    }

    private function acknowledge(string $intentId, int $index, string $hash, string $node): void
    {
        $token = app(MeshTokenService::class)->issue(
            'placement.ack',
            'POST',
            '/api/mesh/internal/placements',
            ['obj' => $intentId, 'idx' => (string) $index, 'sha' => $hash]
        );

        $this->withHeader('X-Erugo-Mesh-Token', $token)
            ->postJson('/api/mesh/internal/placements', [
                'intent_id' => $intentId,
                'index' => $index,
                'hash' => $hash,
                'node' => $node,
            ])
            ->assertOk();
    }

    public function test_create_intent_rejects_a_wrong_chunk_count(): void
    {
        $this->postJson('/api/mesh/uploads', [
            'filename' => 'clip.mp4',
            'size' => 8,
            'total_hash' => str_repeat('f', 64),
            'chunk_hashes' => $this->hashes(1),
        ])->assertStatus(422)
            ->assertJsonPath('data.expected', 2)
            ->assertJsonPath('data.received', 1);
    }

    public function test_create_intent_creates_a_manifest(): void
    {
        $data = $this->createIntent();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $data['intent_id']);
        $this->assertSame('obj_' . $data['intent_id'], $data['object_id']);
        $this->assertSame(4, $data['chunk_size']);
        $this->assertSame(2, $data['chunk_count']);

        $object = app(ManifestRepository::class)->findObject($data['object_id']);
        $this->assertNotNull($object);
        $this->assertSame('sharding', $object->status);
    }

    public function test_chunk_put_verifies_bytes_and_acknowledges_the_coordinator(): void
    {
        $intentId = str_repeat('a', 32);
        $content = 'abcd';
        $hash = hash('sha256', $content);

        $token = app(MeshTokenService::class)->issue(
            'chunk.put',
            'PUT',
            '/n/peer1/chunks/' . $intentId . '/0',
            ['obj' => $intentId, 'idx' => '0', 'sha' => $hash]
        );

        $this->call('PUT', '/n/peer1/chunks/' . $intentId . '/0', [], [], [], [
            'HTTP_X_ERUGO_MESH_TOKEN' => $token,
        ], $content)->assertOk()->assertJsonPath('acknowledged', true);

        $this->assertSame($content, file_get_contents($this->chunkRoot . '/' . $intentId . '/0'));

        Http::assertSent(function ($request) use ($intentId, $hash) {
            return str_contains($request->url(), '/api/mesh/internal/placements')
                && $request['intent_id'] === $intentId
                && $request['hash'] === $hash;
        });
    }

    public function test_chunk_put_refuses_bytes_that_do_not_match_the_token(): void
    {
        $intentId = str_repeat('b', 32);
        $token = app(MeshTokenService::class)->issue(
            'chunk.put',
            'PUT',
            '/n/peer1/chunks/' . $intentId . '/0',
            ['obj' => $intentId, 'idx' => '0', 'sha' => hash('sha256', 'abcd')]
        );

        $this->call('PUT', '/n/peer1/chunks/' . $intentId . '/0', [], [], [], [
            'HTTP_X_ERUGO_MESH_TOKEN' => $token,
        ], 'wxyz')->assertStatus(422)
            ->assertJsonPath('message', 'Chunk hash mismatch');

        $this->assertFalse(file_exists($this->chunkRoot . '/' . $intentId . '/0'));
    }

    public function test_tokens_endpoint_issues_tokens_for_every_configured_node(): void
    {
        $intent = $this->createIntent();

        $this->postJson('/api/mesh/uploads/' . $intent['intent_id'] . '/tokens', ['indices' => [1]])
            ->assertOk()
            ->assertJsonPath('data.chunk_size', 4)
            ->assertJsonStructure(['data' => ['tokens' => ['peer1' => ['1'], 'peer2' => ['1']]]]);
    }

    public function test_enrolled_peers_get_absolute_urls_and_signed_tokens(): void
    {
        app(NodeRepository::class)->enroll([
            'node_id' => 'peer1',
            'endpoint' => 'https://peer1.example.test',
            'public_key' => base64_encode(random_bytes(32)),
            'status' => 'active',
            'revoked_at' => null,
        ]);

        $intent = $this->createIntent();
        $response = $this->postJson(
            '/api/mesh/uploads/' . $intent['intent_id'] . '/tokens',
            ['indices' => [0]]
        )->assertOk();

        $this->assertStringStartsWith(
            'https://peer1.example.test/n/peer1/chunks/',
            $response->json('data.upload_urls.peer1.0')
        );
        $this->assertStringStartsWith('v2.', $response->json('data.tokens.peer1.0'));
        $this->assertStringStartsWith('v1.', $response->json('data.tokens.peer2.0'));
    }

    public function test_commit_requires_two_acks_and_names_the_missing_chunk(): void
    {
        $intent = $this->createIntent();
        $hashes = $this->hashes(2);

        $this->acknowledge($intent['intent_id'], 0, $hashes[0], 'peer1');
        $this->acknowledge($intent['intent_id'], 1, $hashes[1], 'peer1');

        $this->postJson('/api/mesh/uploads/' . $intent['intent_id'] . '/commit')
            ->assertStatus(409)
            ->assertJsonPath('data.missing.0.index', 0)
            ->assertJsonPath('data.missing.1.index', 1);

        $this->acknowledge($intent['intent_id'], 0, $hashes[0], 'peer2');
        $this->acknowledge($intent['intent_id'], 1, $hashes[1], 'peer2');

        $this->postJson('/api/mesh/uploads/' . $intent['intent_id'] . '/commit')
            ->assertOk()
            ->assertJsonPath('data.status', 'sharded');

        $object = app(ManifestRepository::class)->findObject($intent['object_id']);
        $this->assertSame('sharded', $object->status);
    }

    public function test_gc_expires_stale_intents_and_queues_cleanup(): void
    {
        $this->travel(10)->minutes();
        $intent = $this->createIntent();
        $this->travelBack();

        $stale = app(ManifestRepository::class)->findIntent($intent['intent_id']);
        $stale->expires_at = now()->subMinute();
        $stale->save();

        $this->artisan('mesh:gc')->expectsOutputToContain('Expired 1 mesh upload intents');

        $this->assertSame('expired', app(ManifestRepository::class)->findIntent($intent['intent_id'])->status);
        $this->assertSame(1, MeshTombstone::where('object_id', $intent['object_id'])->count());
    }
}
