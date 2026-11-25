<?php

namespace Tests\Feature;

use App\Exceptions\InvalidMeshEnrollmentException;
use App\Services\MeshEnrollmentService;
use App\Services\MeshIdentityService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeshIdentityTest extends TestCase
{
    private string $identityPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->identityPath = sys_get_temp_dir() . '/erugo-mesh-id-' . uniqid('', true) . '.json';
        config([
            'mesh.identity_path' => $this->identityPath,
            'mesh.enrollment_ttl' => 300,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->identityPath)) {
            unlink($this->identityPath);
        }

        parent::tearDown();
    }

    public function test_identity_keys_are_generated_once_and_stored_private(): void
    {
        $identity = app(MeshIdentityService::class);
        $public = $identity->coordinatorPublicKey();
        $peerPublic = $identity->peerPublicKey();

        $this->assertNotSame($public, $peerPublic);
        $this->assertSame($public, app(MeshIdentityService::class)->coordinatorPublicKey());
        $this->assertFileExists($this->identityPath);
        $this->assertSame(0600, fileperms($this->identityPath) & 0777);

        $stored = json_decode((string) file_get_contents($this->identityPath), true);
        $this->assertArrayHasKey('secret', $stored['coordinator']);
        $this->assertArrayHasKey('secret', $stored['peer']);
    }

    public function test_signature_verifies_with_public_key_only(): void
    {
        $identity = app(MeshIdentityService::class);
        $message = 'chunk:0:abc';
        $signature = $identity->sign($message, 'coordinator');

        $this->assertTrue($identity->verify($message, $signature, $identity->coordinatorPublicKey()));
        $this->assertFalse($identity->verify($message . 'x', $signature, $identity->coordinatorPublicKey()));
        $this->assertFalse($identity->verify($message, $signature, $identity->peerPublicKey()));
    }

    public function test_enrollment_blob_round_trips_without_private_key(): void
    {
        $enrollment = app(MeshEnrollmentService::class);
        $blob = $enrollment->createBlob('peer1', 'https://peer1.example.test');

        $claims = $enrollment->parseAndVerify($blob);

        $this->assertSame('peer1', $claims['node']);
        $this->assertSame('https://peer1.example.test', $claims['url']);
        $this->assertStringNotContainsString('secret', $blob);
        $this->assertStringNotContainsString(
            app(MeshIdentityService::class)->peerSecretKey(),
            $blob
        );
    }

    public function test_tampered_enrollment_blob_is_rejected(): void
    {
        $enrollment = app(MeshEnrollmentService::class);
        $blob = $enrollment->createBlob('peer1', 'https://peer1.example.test');
        $parts = explode('.', $blob);
        $parts[0][5] = $parts[0][5] === 'A' ? 'B' : 'A';

        $this->expectException(InvalidMeshEnrollmentException::class);

        $enrollment->parseAndVerify(implode('.', $parts));
    }

    public function test_expired_enrollment_blob_is_rejected(): void
    {
        $enrollment = app(MeshEnrollmentService::class);
        $identity = app(MeshIdentityService::class);
        $blob = $enrollment->createBlob('peer1', 'https://peer1.example.test');

        [$messagePart] = explode('.', $blob);
        $json = $this->base64UrlDecode($messagePart);
        $payload = json_decode($json, true);
        $payload['exp'] = time() - 1;
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = base64_decode($identity->sign($json, 'peer'), true);
        $expiredBlob = $this->base64UrlEncode($json) . '.' . $this->base64UrlEncode($signature);

        $this->expectException(InvalidMeshEnrollmentException::class);
        $this->expectExceptionMessage('expired');

        $enrollment->parseAndVerify($expiredBlob);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - $padding) % 4), true);
    }

    public function test_enrollment_requires_plain_https_data_url(): void
    {
        $enrollment = app(MeshEnrollmentService::class);

        $this->expectException(InvalidMeshEnrollmentException::class);
        $this->expectExceptionMessage('https');

        $enrollment->createBlob('peer1', 'http://peer1.example.test');
    }

    public function test_public_key_endpoint_serves_only_the_public_key(): void
    {
        $publicKey = app(MeshIdentityService::class)->coordinatorPublicKey();

        $this->getJson('/api/mesh/public-key')
            ->assertOk()
            ->assertJsonPath('data.public_key', $publicKey)
            ->assertJsonPath('data.algorithm', 'ed25519')
            ->assertJsonMissingPath('data.secret');
    }

    public function test_peer_init_fetches_coordinator_key_and_prints_a_blob(): void
    {
        config(['mesh.coordinator_url' => 'http://coordinator.test']);
        $coordinatorKey = app(MeshIdentityService::class)->coordinatorPublicKey();

        Http::fake([
            'http://coordinator.test/api/mesh/public-key' => Http::response([
                'status' => 'success',
                'data' => ['public_key' => $coordinatorKey, 'algorithm' => 'ed25519'],
            ]),
        ]);

        $this->artisan('mesh:peer-init', [
            'node' => 'peer2',
            'url' => 'https://peer2.example.test',
        ])->expectsOutputToContain('Paste this blob')->assertExitCode(0);

        $this->assertSame($coordinatorKey, app(MeshIdentityService::class)->storedCoordinatorPublicKey());
    }
}
