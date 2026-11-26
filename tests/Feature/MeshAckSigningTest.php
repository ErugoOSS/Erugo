<?php

namespace Tests\Feature;

use App\Contracts\Mesh\ManifestRepository;
use App\Contracts\Mesh\NodeRepository;
use App\Services\MeshAckSigner;
use App\Services\MeshIdentityService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeshAckSigningTest extends TestCase
{
    private string $identityPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identityPath = sys_get_temp_dir() . '/erugo-mesh-ack-' . uniqid('', true) . '.json';
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'mesh.enabled' => true,
            'mesh.identity_path' => $this->identityPath,
            'mesh.nodes' => ['peer1' => 'https://peer1.example.test'],
        ]);

        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->run();
    }

    protected function tearDown(): void
    {
        if (is_file($this->identityPath)) {
            unlink($this->identityPath);
        }

        parent::tearDown();
    }

    private function createIntent(): array
    {
        $manifests = app(ManifestRepository::class);
        $intentId = str_repeat('a', 32);
        $objectId = 'obj_' . $intentId;

        $manifests->createIntent([
            'intent_id' => $intentId,
            'object_id' => $objectId,
            'user_id' => 1,
            'filename' => 'clip.mp4',
            'size' => 4,
            'total_hash' => str_repeat('f', 64),
            'chunk_size' => 4,
            'chunk_count' => 1,
            'chunk_hashes' => [str_repeat('b', 64)],
            'expires_at' => now()->addMinute(),
        ]);
        $manifests->createObject([
            'object_id' => $objectId,
            'original_name' => 'clip.mp4',
            'size' => 4,
            'chunk_size' => 4,
            'chunk_count' => 1,
            'total_hash' => str_repeat('f', 64),
        ]);

        return [$intentId, $objectId];
    }

    private function ackPayload(string $intentId): array
    {
        return [
            'intent_id' => $intentId,
            'index' => 0,
            'hash' => str_repeat('b', 64),
            'node' => 'peer1',
        ];
    }

    public function test_signed_ack_from_enrolled_peer_is_accepted(): void
    {
        [$intentId, $objectId] = $this->createIntent();
        $nodes = app(NodeRepository::class);
        $nodes->enroll([
            'node_id' => 'peer1',
            'endpoint' => 'https://peer1.example.test',
            'public_key' => app(MeshIdentityService::class)->peerPublicKey(),
            'status' => 'active',
            'revoked_at' => null,
        ]);

        $payload = $this->ackPayload($intentId);
        $signature = app(MeshAckSigner::class)->sign($payload);

        $this->withHeader('X-Erugo-Mesh-Node', 'peer1')
            ->withHeader('X-Erugo-Mesh-Signature', $signature)
            ->postJson('/api/mesh/internal/placements', $payload)
            ->assertOk();

        $chunk = app(ManifestRepository::class)->findChunk($objectId, 0);
        $this->assertSame(1, app(ManifestRepository::class)->acknowledgedPlacementCount($chunk->id));
    }

    public function test_signed_ack_from_revoked_peer_is_rejected(): void
    {
        [$intentId] = $this->createIntent();
        $nodes = app(NodeRepository::class);
        $nodes->enroll([
            'node_id' => 'peer1',
            'endpoint' => 'https://peer1.example.test',
            'public_key' => app(MeshIdentityService::class)->peerPublicKey(),
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        $payload = $this->ackPayload($intentId);
        $signature = app(MeshAckSigner::class)->sign($payload);

        $this->withHeader('X-Erugo-Mesh-Node', 'peer1')
            ->withHeader('X-Erugo-Mesh-Signature', $signature)
            ->postJson('/api/mesh/internal/placements', $payload)
            ->assertUnauthorized();
    }

    public function test_signed_ack_with_wrong_signature_is_rejected(): void
    {
        [$intentId] = $this->createIntent();
        $nodes = app(NodeRepository::class);
        $nodes->enroll([
            'node_id' => 'peer1',
            'endpoint' => 'https://peer1.example.test',
            'public_key' => app(MeshIdentityService::class)->peerPublicKey(),
            'status' => 'active',
            'revoked_at' => null,
        ]);

        $payload = $this->ackPayload($intentId);

        $this->withHeader('X-Erugo-Mesh-Node', 'peer1')
            ->withHeader('X-Erugo-Mesh-Signature', base64_encode(str_repeat('x', 64)))
            ->postJson('/api/mesh/internal/placements', $payload)
            ->assertUnauthorized();
    }
}
