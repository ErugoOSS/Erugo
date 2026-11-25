<?php

namespace App\Http\Controllers;

use App\Services\MeshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeshNodeController extends Controller
{
    /**
     * Cheap liveness endpoint used by the dev harness and the coordinator.
     * It reveals no share data and requires no token.
     */
    public function health(string $node)
    {
        if (!config('mesh.enabled')) {
            return response()->json(['status' => 'error', 'message' => 'Mesh storage is disabled'], 404);
        }

        return response()->json([
            'status' => 'ok',
            'node' => config('mesh.node_id'),
            'requested' => $node,
            'mesh_enabled' => config('mesh.enabled'),
        ]);
    }

    /**
     * Token round-trip proof for the spike. No file data is exposed.
     */
    public function ping(Request $request, string $node)
    {
        $claims = $request->attributes->get('mesh_claims', []);

        return response()->json([
            'status' => 'ok',
            'node' => $node,
            'operation' => $claims['op'] ?? null,
            'object' => $claims['obj'] ?? null,
        ]);
    }

    /**
     * Store one verified shard. The hash is computed from the bytes that
     * actually arrive, never from a client-supplied field.
     */
    public function putChunk(
        Request $request,
        string $node,
        string $intent,
        string $index,
        MeshTokenService $tokens
    ) {
        $claims = $request->attributes->get('mesh_claims', []);
        $index = (int) $index;

        if (!preg_match('/^[a-f0-9]{32}$/', $intent)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid upload id'], 400);
        }

        if (($claims['op'] ?? null) !== 'chunk.put'
            || ($claims['obj'] ?? null) !== $intent
            || ($claims['idx'] ?? null) !== (string) $index) {
            return response()->json(['status' => 'error', 'message' => 'Token does not match chunk'], 401);
        }

        $directory = rtrim((string) config('mesh.chunk_root'), '/') . '/' . $intent;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return response()->json(['status' => 'error', 'message' => 'Chunk storage unavailable'], 500);
        }

        $target = $directory . '/' . $index;
        $temporary = tempnam($directory, 'chunk-');
        if ($temporary === false) {
            return response()->json(['status' => 'error', 'message' => 'Cannot create temporary chunk'], 500);
        }

        $temporaryHandle = fopen($temporary, 'w+b');
        if ($temporaryHandle === false) {
            unlink($temporary);
            return response()->json(['status' => 'error', 'message' => 'Cannot open temporary chunk'], 500);
        }

        $input = $request->getContent(true);
        if (!is_resource($input)) {
            $input = fopen('php://temp', 'r+');
            fwrite($input, $request->getContent());
            rewind($input);
        }

        $context = hash_init('sha256');
        $bytes = 0;
        $maximum = (int) config('mesh.chunk_size');

        while (!feof($input)) {
            $part = fread($input, 65536);
            if ($part === false || $part === '') {
                break;
            }

            $bytes += strlen($part);
            if ($bytes > $maximum) {
                fclose($input);
                fclose($temporaryHandle);
                unlink($temporary);
                return response()->json(['status' => 'error', 'message' => 'Chunk exceeds configured size'], 413);
            }

            fwrite($temporaryHandle, $part);
            hash_update($context, $part);
        }

        fclose($input);
        fclose($temporaryHandle);
        $hash = hash_final($context);

        if (!hash_equals((string) ($claims['sha'] ?? ''), $hash)) {
            unlink($temporary);
            return response()->json(['status' => 'error', 'message' => 'Chunk hash mismatch'], 422);
        }

        if (file_exists($target)) {
            unlink($temporary);
            if (!hash_equals((string) hash_file('sha256', $target), $hash)) {
                return response()->json(['status' => 'error', 'message' => 'Stored chunk hash mismatch'], 409);
            }
        } elseif (!rename($temporary, $target)) {
            unlink($temporary);
            return response()->json(['status' => 'error', 'message' => 'Could not store chunk'], 500);
        }

        if (!chmod($target, 0644)) {
            Log::warning('Could not set mesh chunk permissions: ' . $target);
        }

        $acknowledged = $this->acknowledge($tokens, $intent, $index, $hash, $node);

        return response()->json([
            'status' => $acknowledged ? 'ok' : 'error',
            'node' => $node,
            'index' => $index,
            'hash' => $hash,
            'bytes' => $bytes,
            'acknowledged' => $acknowledged,
        ], $acknowledged ? 200 : 503);
    }

    private function acknowledge(
        MeshTokenService $tokens,
        string $intent,
        int $index,
        string $hash,
        string $node
    ): bool {
        try {
            $token = $tokens->issue(
                'placement.ack',
                'POST',
                '/api/mesh/internal/placements',
                ['obj' => $intent, 'idx' => (string) $index, 'sha' => $hash]
            );

            $response = Http::withHeaders(['X-Erugo-Mesh-Token' => $token])
                ->timeout(10)
                ->post(rtrim((string) config('mesh.coordinator_url'), '/') . '/api/mesh/internal/placements', [
                    'intent_id' => $intent,
                    'index' => $index,
                    'hash' => $hash,
                    'node' => $node,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Mesh acknowledgement failed: ' . $e->getMessage());

            return false;
        }
    }
}
