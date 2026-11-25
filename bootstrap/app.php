<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: [
            'refresh_token',
        ]);

        $middleware->alias([
            'mesh.token' => \App\Http\Middleware\VerifyMeshToken::class,
        ]);

        // Opt in to proxy headers so rate limits and the IP allowlist see the real
        // client address. Read straight from the process environment: this runs
        // while the kernel is being configured, before anything guarantees that the
        // config repository (or .env) is available, and a failure here would take
        // every request down.
        try {
            $trustedProxies = $_ENV['TRUSTED_PROXIES']
                ?? $_SERVER['TRUSTED_PROXIES']
                ?? (getenv('TRUSTED_PROXIES') ?: null);

            if ($trustedProxies === '*') {
                $middleware->trustProxies(at: '*');
            } elseif (is_string($trustedProxies) && trim($trustedProxies) !== '') {
                $middleware->trustProxies(at: array_map('trim', explode(',', $trustedProxies)));
            }
        } catch (\Throwable $e) {
            // Proxy trust is an optimisation; never let it break booting. No
            // logging here either: the exception handler may not be bound yet.
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
