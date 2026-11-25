<?php

namespace Tests\Feature;

use App\Services\MeshTokenService;
use Tests\TestCase;

class MeshNodeRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mesh.enabled' => true,
            'mesh.secret' => str_repeat('s', 64),
            'mesh.node_id' => 'peer1',
        ]);
    }

    public function test_health_is_public_and_reports_the_configured_node(): void
    {
        $this->getJson('/n/peer1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('node', 'peer1')
            ->assertJsonPath('requested', 'peer1');
    }

    public function test_ping_accepts_a_valid_scoped_token(): void
    {
        $token = app(MeshTokenService::class)->issue(
            'mesh.ping',
            'GET',
            '/n/peer1/ping',
            ['obj' => 'object_1']
        );

        $this->withHeader('X-Erugo-Mesh-Token', $token)
            ->getJson('/n/peer1/ping')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('operation', 'mesh.ping')
            ->assertJsonPath('object', 'object_1');
    }

    public function test_ping_rejects_missing_and_malformed_tokens(): void
    {
        $this->getJson('/n/peer1/ping')->assertUnauthorized();

        $this->withHeader('X-Erugo-Mesh-Token', 'not-a-token')
            ->getJson('/n/peer1/ping')
            ->assertUnauthorized();
    }

    public function test_ping_rejects_a_token_issued_for_another_node_path(): void
    {
        $token = app(MeshTokenService::class)->issue(
            'mesh.ping',
            'GET',
            '/n/peer2/ping',
            ['obj' => 'object_1']
        );

        $this->withHeader('X-Erugo-Mesh-Token', $token)
            ->getJson('/n/peer1/ping')
            ->assertUnauthorized();
    }

    public function test_routes_are_disabled_when_mesh_is_off(): void
    {
        config(['mesh.enabled' => false]);

        $this->getJson('/n/peer1/health')
            ->assertNotFound()
            ->assertJsonPath('message', 'Mesh storage is disabled');
    }
}
