<?php

namespace App\Http\Middleware;

use App\Contracts\Mesh\NodeRepository;
use App\Exceptions\InvalidMeshTokenException;
use App\Services\MeshAckSigner;
use App\Services\MeshTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyMeshAck
{
    public function __construct(
        private MeshTokenService $tokens,
        private MeshAckSigner $acks,
        private NodeRepository $nodes
    ) {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('mesh.enabled')) {
            return response()->json(['status' => 'error', 'message' => 'Mesh storage is disabled'], 404);
        }

        $nodeId = $request->header('X-Erugo-Mesh-Node');
        $signature = $request->header('X-Erugo-Mesh-Signature');

        if (is_string($nodeId) && $nodeId !== '' && is_string($signature) && $signature !== '') {
            return $this->verifySignedAck($request, $next, $nodeId, $signature);
        }

        // Legacy peers still send HMAC tokens until they are enrolled.
        $token = $request->header('X-Erugo-Mesh-Token');
        if (!is_string($token) || $token === '') {
            return response()->json(['status' => 'error', 'message' => 'Missing mesh token'], 401);
        }

        try {
            $claims = $this->tokens->verifyForUrl($token, $request->getMethod(), $request->url());
        } catch (InvalidMeshTokenException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        } catch (\Throwable $e) {
            Log::error('Mesh ACK token verification failed: ' . $e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Mesh token service unavailable'], 503);
        }

        $request->attributes->set('mesh_claims', $claims);

        return $next($request);
    }

    private function verifySignedAck(
        Request $request,
        Closure $next,
        string $nodeId,
        string $signature
    ): Response {
        $node = $this->nodes->find($nodeId);

        if (!$node || $node->status !== 'active' || !$node->public_key) {
            return response()->json(['status' => 'error', 'message' => 'Unknown or revoked mesh node'], 401);
        }

        $payload = $request->all();
        foreach (['intent_id', 'index', 'hash', 'node'] as $key) {
            if (!isset($payload[$key]) || !is_scalar($payload[$key])) {
                return response()->json(['status' => 'error', 'message' => 'Malformed acknowledgement'], 400);
            }
        }

        if ($payload['node'] !== $nodeId || !$this->acks->verify($payload, $signature, $node->public_key)) {
            return response()->json(['status' => 'error', 'message' => 'Bad acknowledgement signature'], 401);
        }

        $request->attributes->set('mesh_claims', [
            'op' => 'placement.ack',
            'obj' => (string) $payload['intent_id'],
            'idx' => (string) $payload['index'],
            'sha' => (string) $payload['hash'],
            'node' => $nodeId,
        ]);

        return $next($request);
    }
}
