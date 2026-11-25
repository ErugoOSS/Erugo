<?php

namespace App\Services;

use App\Exceptions\InvalidMeshTokenException;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived, scoped credentials for the mesh data plane.
 *
 * Deliberately not Laravel signed URLs: those are bound to APP_KEY, which is
 * not shared with storage peers. The mesh secret is separate and only the
 * coordinator issues tokens; peers only verify them.
 */
class MeshTokenService
{
    private const VERSION = 'v1';

    private const MIN_SECRET_LENGTH = 32;

    /**
     * Issue a token bound to one operation, one HTTP method and one path.
     *
     * Supported claims: obj (object id), idx (chunk index or ''), sha (hex
     * SHA-256 or ''). Every claim is signed; none is trusted from the query
     * string at verification time.
     */
    public function issue(
        string $operation,
        string $method,
        string $path,
        array $claims,
        ?int $ttlSeconds = null
    ): string {
        $secret = $this->secret();
        $path = $this->normalisePath($path);

        $this->assertOperation($operation);
        $this->assertMethod($method);
        $this->assertPath($path);

        $object = $claims['obj'] ?? null;
        $index = $claims['idx'] ?? '';
        $hash = $claims['sha'] ?? '';

        $this->assertObject($object);
        $this->assertIndex($index);
        $this->assertHash($hash);

        $ttl = $ttlSeconds ?? (int) config('mesh.token_ttl');
        $ttl = min(max(30, $ttl), (int) config('mesh.max_token_ttl'));

        $payload = [
            'v' => self::VERSION,
            'op' => $operation,
            'm' => strtoupper($method),
            'p' => $path,
            'obj' => $object,
            'idx' => (string) $index,
            'sha' => (string) $hash,
            'exp' => time() + $ttl,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        $signature = hash_hmac('sha256', $this->canonical($payload), $secret, true);

        return self::VERSION . '.' . $this->encode($payload) . '.' . $this->encode($signature);
    }

    /**
     * Verify a token and return its signed claims.
     *
     * @throws InvalidMeshTokenException
     */
    public function verify(string $token, string $method, string $path): array
    {
        $secret = $this->secret();
        $parts = explode('.', $token);

        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            throw new InvalidMeshTokenException('Malformed mesh token');
        }

        $payload = json_decode($this->decode($parts[1]), true);
        $signature = $this->decode($parts[2]);

        if (!is_array($payload) || strlen($signature) !== 32) {
            throw new InvalidMeshTokenException('Malformed mesh token');
        }

        $this->assertPayload($payload);

        $expected = hash_hmac('sha256', $this->canonical($payload), $secret, true);
        if (!hash_equals($expected, $signature)) {
            throw new InvalidMeshTokenException('Bad mesh token signature');
        }

        if (!hash_equals(strtoupper($method), $payload['m'])) {
            throw new InvalidMeshTokenException('Mesh token method mismatch');
        }

        if (!hash_equals($this->normalisePath($path), $payload['p'])) {
            throw new InvalidMeshTokenException('Mesh token path mismatch');
        }

        $remaining = $payload['exp'] - time();
        if ($remaining <= 0 || $remaining > (int) config('mesh.max_token_ttl')) {
            throw new InvalidMeshTokenException('Mesh token expired or invalid lifetime');
        }

        // A token is single use. Fail closed if the replay store is unavailable:
        // accepting an unrecorded nonce would make replay protection optional.
        $replayKey = 'mesh:token:' . hash('sha256', $payload['nonce']);
        try {
            $stored = Cache::add($replayKey, 1, $remaining);
        } catch (\Throwable $e) {
            throw new InvalidMeshTokenException('Mesh token replay store unavailable');
        }

        if (!$stored) {
            throw new InvalidMeshTokenException('Mesh token already used');
        }

        return $payload;
    }

    /**
     * Fields are concatenated in a fixed order with an unambiguous separator.
     * Adding a field here also requires updating assertPayload().
     */
    private function canonical(array $payload): string
    {
        return implode("\n", [
            'erugo-mesh-' . self::VERSION,
            $payload['op'],
            $payload['m'],
            $payload['p'],
            $payload['obj'],
            $payload['idx'],
            $payload['sha'],
            (string) $payload['exp'],
            $payload['nonce'],
        ]);
    }

    private function assertPayload(array $payload): void
    {
        $required = ['v', 'op', 'm', 'p', 'obj', 'idx', 'sha', 'exp', 'nonce'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new InvalidMeshTokenException('Incomplete mesh token');
            }
        }

        if ($payload['v'] !== self::VERSION) {
            throw new InvalidMeshTokenException('Unsupported mesh token version');
        }

        $this->assertOperation($payload['op']);
        $this->assertMethod($payload['m']);
        $this->assertPath($payload['p']);
        $this->assertObject($payload['obj']);
        $this->assertIndex($payload['idx']);
        $this->assertHash($payload['sha']);

        if (!is_int($payload['exp'])) {
            throw new InvalidMeshTokenException('Invalid mesh token expiry');
        }

        if (!is_string($payload['nonce']) || !preg_match('/^[a-f0-9]{32}$/', $payload['nonce'])) {
            throw new InvalidMeshTokenException('Invalid mesh token nonce');
        }
    }

    private function secret(): string
    {
        $secret = config('mesh.secret');

        if (!is_string($secret) || strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new \RuntimeException('Mesh secret is not configured');
        }

        return $secret;
    }

    private function assertOperation(mixed $operation): void
    {
        if (!is_string($operation) || !preg_match('/^[a-z]+(?:\.[a-z]+)+$/', $operation) || strlen($operation) > 64) {
            throw new InvalidMeshTokenException('Invalid mesh operation');
        }
    }

    private function assertMethod(mixed $method): void
    {
        if (!is_string($method) || !in_array(strtoupper($method), ['GET', 'PUT', 'POST', 'DELETE', 'HEAD'], true)) {
            throw new InvalidMeshTokenException('Invalid mesh method');
        }
    }

    private function assertPath(mixed $path): void
    {
        if (!is_string($path) || $path === '' || strlen($path) > 2048 || str_contains($path, '?') || str_contains($path, '#')) {
            throw new InvalidMeshTokenException('Invalid mesh path');
        }
    }

    private function assertObject(mixed $object): void
    {
        if (!is_string($object) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/', $object)) {
            throw new InvalidMeshTokenException('Invalid mesh object id');
        }
    }

    private function assertIndex(mixed $index): void
    {
        if (!is_string($index) && !is_int($index)) {
            throw new InvalidMeshTokenException('Invalid mesh chunk index');
        }

        $value = (string) $index;
        if ($value !== '' && !preg_match('/^(0|[1-9][0-9]{0,8})$/', $value)) {
            throw new InvalidMeshTokenException('Invalid mesh chunk index');
        }
    }

    private function assertHash(mixed $hash): void
    {
        if (!is_string($hash) || ($hash !== '' && !preg_match('/^[a-f0-9]{64}$/', $hash))) {
            throw new InvalidMeshTokenException('Invalid mesh chunk hash');
        }
    }

    private function normalisePath(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);

        return ltrim(is_string($parsed) ? $parsed : $path, '/');
    }

    private function encode(string|array $value): string
    {
        $json = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding === 1) {
            throw new InvalidMeshTokenException('Invalid mesh token encoding');
        }

        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - $padding) % 4), true);

        if ($decoded === false) {
            throw new InvalidMeshTokenException('Invalid mesh token encoding');
        }

        return $decoded;
    }
}
