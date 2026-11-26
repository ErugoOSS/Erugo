<?php

namespace App\Http\Controllers;

use App\Services\MeshIdentityService;

class MeshPublicKeyController extends Controller
{
    /**
     * The coordinator's public key is safe to serve without authentication:
     * peers need it to verify signed chunk tokens.
     */
    public function show(MeshIdentityService $identity)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'public_key' => $identity->coordinatorPublicKey(),
                'algorithm' => 'ed25519',
            ],
        ]);
    }
}
