<?php

namespace App\Services;

use App\Exceptions\InvalidMeshEnrollmentException;

/**
 * Creates and verifies peer-generated registration blobs.
 *
 * A blob proves possession of the peer private key and carries only the public
 * key, node id and public HTTPS data URL. Admin approval is still required.
 */
class MeshEnrollmentService
{
    public function __construct(private MeshIdentityService $identity)
    {
        //
    }

    public function createBlob(string $nodeId, string $dataUrl, ?int $ttlSeconds = null): string
    {
        $this->assertNodeId($nodeId);
        $this->assertDataUrl($dataUrl);

        $ttl = $ttlSeconds ?? (int) config('mesh.enrollment_ttl');
        $payload = [
            'v' => 1,
            'node' => $nodeId,
            'url' => rtrim($dataUrl, '/'),
            'pub' => $this->identity->peerPublicKey(),
            'exp' => time() + max(30, $ttl),
            'nonce' => bin2hex(random_bytes(16)),
        ];

        $json = $this->encode($payload);
        $message = $this->base64UrlEncode($json);
        $signature = base64_decode($this->identity->sign($json, 'peer'), true);

        if ($signature === false) {
            throw new InvalidMeshEnrollmentException('Could not sign enrollment payload');
        }

        return $message . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * @return array{v:int,node:string,url:string,pub:string,exp:int,nonce:string}
     */
    public function parseAndVerify(string $blob): array
    {
        $parts = explode('.', $blob);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidMeshEnrollmentException('Malformed enrollment blob');
        }

        $json = $this->base64UrlDecode($parts[0]);
        $signature = $this->base64UrlDecode($parts[1]);
        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            throw new InvalidMeshEnrollmentException('Malformed enrollment payload');
        }

        foreach (['v', 'node', 'url', 'pub', 'exp', 'nonce'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new InvalidMeshEnrollmentException('Incomplete enrollment payload');
            }
        }

        if ($payload['v'] !== 1) {
            throw new InvalidMeshEnrollmentException('Unsupported enrollment version');
        }

        $this->assertNodeId($payload['node']);
        $this->assertDataUrl($payload['url']);

        if (!is_int($payload['exp']) || $payload['exp'] <= time()) {
            throw new InvalidMeshEnrollmentException('Enrollment blob expired');
        }

        if (!is_string($payload['nonce']) || !preg_match('/^[a-f0-9]{32}$/', $payload['nonce'])) {
            throw new InvalidMeshEnrollmentException('Invalid enrollment nonce');
        }

        if (!is_string($payload['pub']) || !$this->identity->verify($json, base64_encode($signature), $payload['pub'])) {
            throw new InvalidMeshEnrollmentException('Bad enrollment signature');
        }

        return $payload;
    }

    private function assertNodeId(mixed $nodeId): void
    {
        if (!is_string($nodeId) || !preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $nodeId)) {
            throw new InvalidMeshEnrollmentException('Invalid node id');
        }
    }

    private function assertDataUrl(mixed $url): void
    {
        if (!is_string($url) || strlen($url) > 512) {
            throw new InvalidMeshEnrollmentException('Invalid data URL');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidMeshEnrollmentException('Data URL must be a plain https URL');
        }
    }

    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding === 1) {
            throw new InvalidMeshEnrollmentException('Invalid enrollment encoding');
        }

        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - $padding) % 4), true);

        if ($decoded === false) {
            throw new InvalidMeshEnrollmentException('Invalid enrollment encoding');
        }

        return $decoded;
    }
}
