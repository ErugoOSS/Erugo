<?php

namespace App\Http\Controllers;

use App\Contracts\Mesh\ManifestRepository;
use App\Contracts\Mesh\NodeRepository;
use Illuminate\Http\Request;

class MeshPlacementController extends Controller
{
    /**
     * Internal callback from a storage peer after it has verified a chunk.
     * The peer is authenticated by the mesh HMAC, not by a user session; the
     * coordinator still cross-checks the callback against the signed claims.
     */
    public function store(
        Request $request,
        ManifestRepository $manifests,
        NodeRepository $nodes
    ) {
        $claims = $request->attributes->get('mesh_claims', []);

        $validated = $request->validate([
            'intent_id' => ['required', 'string'],
            'index' => ['required', 'integer', 'min:0'],
            'hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'node' => ['required', 'string', 'max:64'],
        ]);

        $matches = ($claims['op'] ?? null) === 'placement.ack'
            && ($claims['obj'] ?? null) === $validated['intent_id']
            && ($claims['idx'] ?? null) === (string) $validated['index']
            && ($claims['sha'] ?? null) === $validated['hash'];

        if (!$matches) {
            return response()->json(['status' => 'error', 'message' => 'Acknowledgement does not match token'], 401);
        }

        if (isset($claims['node']) && $claims['node'] !== $validated['node']) {
            return response()->json(['status' => 'error', 'message' => 'Acknowledgement does not match token'], 401);
        }

        $configuredNodes = config('mesh.nodes');
        $registered = $nodes->find($validated['node']);
        $endpoint = $registered && $registered->public_key
            ? $registered->endpoint
            : (is_array($configuredNodes) ? ($configuredNodes[$validated['node']] ?? null) : null);

        if (!is_string($endpoint) || $endpoint === '') {
            return response()->json(['status' => 'error', 'message' => 'Unknown mesh node'], 400);
        }

        $intent = $manifests->findIntent($validated['intent_id']);
        if (!$intent || $intent->status !== 'pending') {
            return response()->json(['status' => 'error', 'message' => 'Upload intent is not active'], 410);
        }

        $expectedHash = $intent->chunk_hashes[$validated['index']] ?? null;
        if (!is_string($expectedHash) || !hash_equals($expectedHash, $validated['hash'])) {
            return response()->json(['status' => 'error', 'message' => 'Chunk hash does not match intent'], 409);
        }

        $node = $nodes->upsert($validated['node'], $endpoint);

        $chunkSize = min(
            (int) $intent->chunk_size,
            max(1, (int) $intent->size - $validated['index'] * (int) $intent->chunk_size)
        );

        $chunk = $manifests->findChunk($intent->object_id, $validated['index'])
            ?? $manifests->addChunk($intent->object_id, $validated['index'], $validated['hash'], $chunkSize);

        $manifests->recordPlacement(
            $chunk->id,
            $node->node_id,
            'acknowledged',
            $validated['hash']
        );

        return response()->json(['status' => 'success', 'data' => ['ok' => true]]);
    }
}
