<?php

namespace App\Http\Controllers;

use App\Contracts\Mesh\ManifestRepository;
use App\Services\MeshTokenService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeshUploadController extends Controller
{
    /**
     * Start a sharded upload. The client supplies the hashes so peers can be
     * told what to verify; the coordinator never trusts those hashes until a
     * peer acknowledges the bytes it actually received.
     */
    public function createIntent(
        Request $request,
        ManifestRepository $manifests,
        SettingsService $settings
    ) {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'total_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'chunk_hashes' => ['required', 'array', 'min:1'],
            'chunk_hashes.*' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        $size = (int) $validated['size'];
        $chunkSize = (int) config('mesh.chunk_size');
        $hashes = array_values($validated['chunk_hashes']);
        $expectedChunks = (int) ceil($size / $chunkSize);

        if ($expectedChunks !== count($hashes)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chunk hash count does not match the file size',
                'data' => ['expected' => $expectedChunks, 'received' => count($hashes)],
            ], 422);
        }

        if ($expectedChunks > (int) config('mesh.max_chunks')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Too many chunks for one upload',
            ], 413);
        }

        $configuredMax = $settings->getMaxUploadSize();
        $hardMax = (int) config('mesh.max_upload_bytes');
        if ($size > $hardMax || ($configuredMax !== null && $size > $configuredMax)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File exceeds the maximum allowed size',
            ], 413);
        }

        $intentId = bin2hex(random_bytes(16));
        $objectId = 'obj_' . $intentId;
        $ttl = (int) config('mesh.token_ttl');
        $expiresAt = now()->addSeconds($ttl * 4);

        DB::transaction(function () use (
            $request,
            $manifests,
            $intentId,
            $objectId,
            $validated,
            $size,
            $chunkSize,
            $expectedChunks,
            $hashes,
            $expiresAt
        ) {
            $manifests->createIntent([
                'intent_id' => $intentId,
                'object_id' => $objectId,
                'user_id' => $request->user()->id,
                'filename' => $validated['filename'],
                'size' => $size,
                'total_hash' => $validated['total_hash'],
                'chunk_size' => $chunkSize,
                'chunk_count' => $expectedChunks,
                'chunk_hashes' => $hashes,
                'expires_at' => $expiresAt,
            ]);

            $manifests->createObject([
                'object_id' => $objectId,
                'original_name' => $validated['filename'],
                'size' => $size,
                'chunk_size' => $chunkSize,
                'chunk_count' => $expectedChunks,
                'total_hash' => $validated['total_hash'],
            ]);
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'intent_id' => $intentId,
                'object_id' => $objectId,
                'chunk_size' => $chunkSize,
                'chunk_count' => $expectedChunks,
                'expires_at' => $expiresAt,
            ],
        ], 201);
    }

    /**
     * Issue fresh tokens for chunk PUTs. Tokens are single use, so a long or
     * resumed upload needs a way to ask for more without restarting.
     */
    public function tokens(
        Request $request,
        string $intentId,
        ManifestRepository $manifests,
        MeshTokenService $tokens
    ) {
        $intent = $manifests->findIntent($intentId);

        if (!$intent || $intent->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Upload intent not found'], 404);
        }

        if ($intent->status !== 'pending' || $intent->expires_at->isPast()) {
            return response()->json(['status' => 'error', 'message' => 'Upload intent is no longer active'], 410);
        }

        $validated = $request->validate([
            'indices' => ['sometimes', 'array', 'min:1'],
            'indices.*' => ['integer', 'min:0'],
        ]);

        $indices = $validated['indices'] ?? range(0, $intent->chunk_count - 1);
        $hashes = $intent->chunk_hashes;
        $nodes = config('mesh.nodes');
        $tokensByNode = [];

        foreach ($indices as $index) {
            if (!array_key_exists($index, $hashes)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Chunk index out of range',
                ], 422);
            }

            foreach (array_keys($nodes) as $nodeId) {
                $path = '/n/' . $nodeId . '/chunks/' . $intentId . '/' . $index;
                $tokensByNode[$nodeId][$index] = $tokens->issue(
                    'chunk.put',
                    'PUT',
                    $path,
                    ['obj' => $intentId, 'idx' => (string) $index, 'sha' => $hashes[$index]]
                );
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'tokens' => $tokensByNode,
                'chunk_size' => $intent->chunk_size,
            ],
        ]);
    }

    /**
     * Commit only when every chunk has the configured number of peer ACKs.
     * A shortfall is reported by index so the client can resume exactly those
     * chunks instead of starting the upload over.
     */
    public function commit(
        Request $request,
        string $intentId,
        ManifestRepository $manifests
    ) {
        $intent = $manifests->findIntent($intentId);

        if (!$intent || $intent->user_id !== $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Upload intent not found'], 404);
        }

        if ($intent->status !== 'pending') {
            return response()->json(['status' => 'error', 'message' => 'Upload intent is no longer active'], 410);
        }

        if ($intent->expires_at->isPast()) {
            return response()->json(['status' => 'error', 'message' => 'Upload intent expired'], 410);
        }

        $required = (int) config('mesh.write_copies');
        $missing = [];

        foreach ($intent->chunk_hashes as $index => $hash) {
            $chunk = $manifests->findChunk($intent->object_id, $index);
            $acknowledged = $chunk ? $manifests->acknowledgedPlacementCount($chunk->id) : 0;

            if ($acknowledged < $required) {
                $missing[] = [
                    'index' => $index,
                    'acknowledged' => $acknowledged,
                    'required' => $required,
                ];
            }
        }

        if ($missing !== []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chunks missing required acknowledgements',
                'data' => ['missing' => $missing],
            ], 409);
        }

        DB::transaction(function () use ($manifests, $intent) {
            $manifests->setIntentStatus($intent->intent_id, 'committed');
            $manifests->setObjectStatus($intent->object_id, 'sharded');
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'object_id' => $intent->object_id,
                'chunk_count' => $intent->chunk_count,
                'status' => 'sharded',
            ],
        ]);
    }
}
