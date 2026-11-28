<?php

use App\Http\Middleware\EnsureLivesharesAvailable;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\TrustProxies as IlluminateTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Register App API routes at /api/app/v1
            Route::middleware('api')
                ->prefix('api/app/v1')
                ->namespace('App\Http\Controllers\Api\App')
                ->group(base_path('routes/api_app.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: [
            'refresh_token',
        ]);

        $middleware->replace(IlluminateTrustProxies::class, TrustProxies::class);

        // Check the live shares flag before cookies, sessions, throttling and
        // auth, so a switched-off route answers like one that doesn't exist.
        $middleware->prependToPriorityList(
            before: \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            prepend: EnsureLivesharesAvailable::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (!($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
            $message = 'Too many attempts. Please try again later.';
            if ($retryAfter !== null) {
                $message = "Too many attempts. Please try again in {$retryAfter} seconds.";
            }

            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], 429, $e->getHeaders());
        });
    })
    ->create();
