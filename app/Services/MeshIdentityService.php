<?php

namespace App\Services;

use RuntimeException;

/**
 * Local Ed25519 identities for mesh nodes.
 *
 * The coordinator signs chunk tokens with its coordinator key. A peer signs
 * its own ACKs and enrollment blobs with its peer key. Only public keys are
 * ever stored in the database or sent over the wire.
 */
class MeshIdentityService
{
    private ?array $identity = null;

    public function coordinatorPublicKey(): string
    {
        return $this->keys('coordinator')['public'];
    }

    public function coordinatorSecretKey(): string
    {
        return $this->keys('coordinator')['secret'];
    }

    public function peerPublicKey(): string
    {
        return $this->keys('peer')['public'];
    }

    public function peerSecretKey(): string
    {
        return $this->keys('peer')['secret'];
    }

    /**
     * Public key of the coordinator, as learned by this peer. Null on the
     * coordinator itself until a peer stores one.
     */
    public function storedCoordinatorPublicKey(): ?string
    {
        $identity = $this->load();

        return $identity['peer']['coordinator_public'] ?? null;
    }

    public function rememberCoordinatorPublicKey(string $publicKey): void
    {
        $this->assertPublicKey($publicKey);

        $identity = $this->load();
        $identity['peer']['coordinator_public'] = $publicKey;
        $this->save($identity);
    }

    public function sign(string $message, string $role): string
    {
        $secret = $role === 'coordinator' ? $this->coordinatorSecretKey() : $this->peerSecretKey();

        return base64_encode(sodium_crypto_sign_detached($message, $this->decodeKey($secret, 64)));
    }

    public function verify(string $message, string $signature, string $publicKey): bool
    {
        $this->assertPublicKey($publicKey);

        $decodedSignature = $this->decodeKey($signature, SODIUM_CRYPTO_SIGN_BYTES);
        if ($decodedSignature === null) {
            return false;
        }

        $decodedPublicKey = $this->decodeKey($publicKey, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
        if ($decodedPublicKey === null) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($decodedSignature, $message, $decodedPublicKey);
        } catch (\SodiumException) {
            return false;
        }
    }

    private function keys(string $role): array
    {
        $identity = $this->load();

        if (!isset($identity[$role]['public'], $identity[$role]['secret'])) {
            $pair = sodium_crypto_sign_keypair();
            $identity[$role] = [
                'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
                'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            ];
            $this->save($identity);
        }

        return $identity[$role];
    }

    private function load(): array
    {
        if ($this->identity !== null) {
            return $this->identity;
        }

        $path = (string) config('mesh.identity_path');
        if (!is_file($path)) {
            return $this->identity = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Mesh identity file is not valid JSON: ' . $path);
        }

        return $this->identity = $decoded;
    }

    private function save(array $identity): void
    {
        $path = (string) config('mesh.identity_path');
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create mesh identity directory: ' . $directory);
        }

        $temporary = tempnam($directory, 'identity-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create temporary mesh identity file');
        }

        $json = json_encode($identity, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        file_put_contents($temporary, $json, LOCK_EX);
        chmod($temporary, 0600);

        if (!rename($temporary, $path)) {
            unlink($temporary);
            throw new RuntimeException('Could not save mesh identity file: ' . $path);
        }

        chmod($path, 0600);
        $this->identity = $identity;
    }

    private function assertPublicKey(string $publicKey): void
    {
        if ($this->decodeKey($publicKey, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) === null) {
            throw new RuntimeException('Invalid Ed25519 public key');
        }
    }

    private function decodeKey(string $encoded, int $expectedLength): ?string
    {
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || strlen($decoded) !== $expectedLength || base64_encode($decoded) !== $encoded) {
            return null;
        }

        return $decoded;
    }
}
