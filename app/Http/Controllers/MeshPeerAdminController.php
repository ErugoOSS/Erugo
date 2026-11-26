<?php

namespace App\Http\Controllers;

use App\Contracts\Mesh\NodeRepository;
use App\Exceptions\InvalidMeshEnrollmentException;
use App\Models\MeshEnrollmentNonce;
use App\Services\MeshEnrollmentService;
use Illuminate\Http\Request;

class MeshPeerAdminController extends Controller
{
    public function index(NodeRepository $nodes)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'peers' => $nodes->enrolled()->map(function ($node) {
                    return [
                        'node_id' => $node->node_id,
                        'endpoint' => $node->endpoint,
                        'status' => $node->status,
                        'key_fingerprint' => $node->public_key
                            ? substr(hash('sha256', base64_decode($node->public_key)), 0, 16)
                            : null,
                        'revoked_at' => $node->revoked_at,
                        'last_seen_at' => $node->last_seen_at,
                    ];
                }),
            ],
        ]);
    }

    public function store(
        Request $request,
        MeshEnrollmentService $enrollment,
        NodeRepository $nodes
    ) {
        $validated = $request->validate([
            'blob' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $claims = $enrollment->parseAndVerify($validated['blob']);
        } catch (InvalidMeshEnrollmentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($claims['node'] === 'coordinator') {
            return response()->json([
                'status' => 'error',
                'message' => 'Coordinator cannot enroll as a peer',
            ], 422);
        }

        // Single-use blob is checked first so a replay is named even when
        // the peer it names is already enrolled.
        try {
            MeshEnrollmentNonce::create(['nonce' => $claims['nonce']]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'This enrollment blob was already used',
            ], 409);
        }

        $existing = $nodes->find($claims['node']);

        if ($existing && $existing->status === 'active') {
            $message = $existing->public_key === $claims['pub']
                ? 'This peer is already enrolled'
                : 'Revoke this node id before enrolling a different key';

            return response()->json(['status' => 'error', 'message' => $message], 409);
        }

        $node = $nodes->enroll([
            'node_id' => $claims['node'],
            'endpoint' => $claims['url'],
            'public_key' => $claims['pub'],
            'status' => 'active',
            'revoked_at' => null,
            'healthy' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'node_id' => $node->node_id,
                'endpoint' => $node->endpoint,
                'key_fingerprint' => substr(hash('sha256', base64_decode($node->public_key)), 0, 16),
            ],
        ], 201);
    }

    public function destroy(string $nodeId, NodeRepository $nodes)
    {
        if (!$nodes->revoke($nodeId)) {
            return response()->json(['status' => 'error', 'message' => 'Peer not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => ['node_id' => $nodeId]]);
    }
}
