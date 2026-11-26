<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MeshEnrollmentService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeshEnrollmentAdminTest extends TestCase
{
    private string $identityPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identityPath = sys_get_temp_dir() . '/erugo-mesh-admin-' . uniqid('', true) . '.json';
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'mesh.identity_path' => $this->identityPath,
            'mesh.enrollment_ttl' => 300,
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

    private function blob(string $node = 'peer1', string $url = 'https://peer1.example.test'): string
    {
        return app(MeshEnrollmentService::class)->createBlob($node, $url);
    }

    public function test_non_admin_cannot_manage_mesh_peers(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]));

        $this->getJson('/api/settings/mesh-peers')->assertForbidden();
        $this->postJson('/api/settings/mesh-peers', ['blob' => $this->blob()])->assertForbidden();
    }

    public function test_admin_can_approve_list_and_revoke_a_peer(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));
        $blob = $this->blob();

        $this->postJson('/api/settings/mesh-peers', ['blob' => $blob])
            ->assertCreated()
            ->assertJsonPath('data.node_id', 'peer1')
            ->assertJsonPath('data.endpoint', 'https://peer1.example.test');

        $this->getJson('/api/settings/mesh-peers')
            ->assertOk()
            ->assertJsonPath('data.peers.0.node_id', 'peer1')
            ->assertJsonPath('data.peers.0.status', 'active');

        $this->deleteJson('/api/settings/mesh-peers/peer1')->assertOk();

        $this->getJson('/api/settings/mesh-peers')
            ->assertOk()
            ->assertJsonPath('data.peers.0.status', 'revoked');
    }

    public function test_blob_is_single_use(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));
        $blob = $this->blob();

        $this->postJson('/api/settings/mesh-peers', ['blob' => $blob])->assertCreated();
        $this->postJson('/api/settings/mesh-peers', ['blob' => $blob])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This enrollment blob was already used');
    }

    public function test_malformed_blob_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $this->postJson('/api/settings/mesh-peers', ['blob' => 'not-a-blob'])
            ->assertStatus(422);
    }
}
