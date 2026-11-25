<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidMeshTokenException;
use App\Services\MeshTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyMeshToken
{
    public function __construct(private MeshTokenService $tokens)
    {
        //
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('mesh.enabled')) {
            return response()->json(['status' => 'error', 'message' => 'Mesh storage is disabled'], 404);
        }

        $token = $request->header('X-Erugo-Mesh-Token');

        if (!is_string($token) || $token === '') {
            return response()->json(['status' => 'error', 'message' => 'Missing mesh token'], 401);
        }

        try {
            $claims = $this->tokens->verify($token, $request->getMethod(), $request->path());
        } catch (InvalidMeshTokenException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 401);
        } catch (\Throwable $e) {
            Log::error('Mesh token verification failed: ' . $e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Mesh token service unavailable'], 503);
        }

        $request->attributes->set('mesh_claims', $claims);

        return $next($request);
    }
}
