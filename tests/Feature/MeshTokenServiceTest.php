<?php

namespace Tests\Feature;

use App\Exceptions\InvalidMeshTokenException;
use App\Services\MeshTokenService;
use Tests\TestCase;

class MeshTokenServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mesh.enabled' => true,
            'mesh.secret' => str_repeat('s', 64),
            'mesh.node_id' => 'peer1',
            'mesh.token_ttl' => 300,
            'mesh.max_token_ttl' => 900,
        ]);
    }

    public function test_signed_token_verifies_against_its_method_and_path(): void
    {
        $tokens = app(MeshTokenService::class);
        $hash = str_repeat('a', 64);

        $token = $tokens->issue(
            'chunk.put',
            'PUT',
            '/n/peer1/chunks/object_1/0',
            ['obj' => 'object_1', 'idx' => '0', 'sha' => $hash]
        );

        $claims = $tokens->verify($token, 'PUT', 'n/peer1/chunks/object_1/0');

        $this->assertSame('chunk.put', $claims['op']);
        $this->assertSame('object_1', $claims['obj']);
        $this->assertSame('0', $claims['idx']);
        $this->assertSame($hash, $claims['sha']);
    }

    public function test_token_is_rejected_for_a_different_method(): void
    {
        $tokens = app(MeshTokenService::class);
        $token = $tokens->issue('mesh.ping', 'GET', '/n/peer1/ping', ['obj' => 'object_1']);

        $this->expectException(InvalidMeshTokenException::class);
        $this->expectExceptionMessage('method mismatch');

        $tokens->verify($token, 'POST', 'n/peer1/ping');
    }

    public function test_token_is_rejected_for_a_different_path(): void
    {
        $tokens = app(MeshTokenService::class);
        $token = $tokens->issue('mesh.ping', 'GET', '/n/peer2/ping', ['obj' => 'object_1']);

        $this->expectException(InvalidMeshTokenException::class);
        $this->expectExceptionMessage('path mismatch');

        $tokens->verify($token, 'GET', 'n/peer1/ping');
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $tokens = app(MeshTokenService::class);
        $token = $tokens->issue('mesh.ping', 'GET', '/n/peer1/ping', ['obj' => 'object_1']);
        $parts = explode('.', $token);
        $parts[1][10] = $parts[1][10] === 'A' ? 'B' : 'A';

        $this->expectException(InvalidMeshTokenException::class);

        $tokens->verify(implode('.', $parts), 'GET', 'n/peer1/ping');
    }

    public function test_token_cannot_be_replayed(): void
    {
        $tokens = app(MeshTokenService::class);
        $token = $tokens->issue('mesh.ping', 'GET', '/n/peer1/ping', ['obj' => 'object_1']);

        $tokens->verify($token, 'GET', 'n/peer1/ping');

        $this->expectException(InvalidMeshTokenException::class);
        $this->expectExceptionMessage('already used');

        $tokens->verify($token, 'GET', 'n/peer1/ping');
    }

    public function test_token_with_an_oversized_lifetime_is_rejected(): void
    {
        $tokens = app(MeshTokenService::class);
        $token = $tokens->issue('mesh.ping', 'GET', '/n/peer1/ping', ['obj' => 'object_1']);

        config(['mesh.max_token_ttl' => 60]);

        $this->expectException(InvalidMeshTokenException::class);
        $this->expectExceptionMessage('invalid lifetime');

        $tokens->verify($token, 'GET', 'n/peer1/ping');
    }

    public function test_short_secret_is_refused(): void
    {
        config(['mesh.secret' => 'too-short']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not configured');

        app(MeshTokenService::class)->issue('mesh.ping', 'GET', '/n/peer1/ping', ['obj' => 'object_1']);
    }
}
