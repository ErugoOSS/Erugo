<?php

namespace App\Services;

/**
 * Signs and verifies placement acknowledgements with a peer's Ed25519 key.
 * The canonical form is field-based so JSON key order cannot change a signature.
 */
class MeshAckSigner
{
    public function __construct(private MeshIdentityService $identity)
    {
        //
    }

    public function canonical(array $payload): string
    {
        return implode("\n", [
            'mesh-ack-v1',
            (string) ($payload['intent_id'] ?? ''),
            (string) ($payload['index'] ?? ''),
            (string) ($payload['hash'] ?? ''),
            (string) ($payload['node'] ?? ''),
        ]);
    }

    public function sign(array $payload): string
    {
        return $this->identity->sign($this->canonical($payload), 'peer');
    }

    public function verify(array $payload, string $signature, string $publicKey): bool
    {
        return $this->identity->verify($this->canonical($payload), $signature, $publicKey);
    }
}
