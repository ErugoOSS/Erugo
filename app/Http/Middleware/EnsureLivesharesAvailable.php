<?php

namespace App\Http\Middleware;

use App\Support\Liveshares;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnsureLivesharesAvailable
{
    /**
     * When the ERUGO_LIVESHARES flag is off, answer live-share routes with the
     * same 404 Laravel gives for a route that doesn't exist.
     *
     * bootstrap/app.php puts this at the top of the middleware priority list,
     * so it runs before auth and a guest gets 404 rather than 401.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Liveshares::available()) {
            throw new NotFoundHttpException(sprintf('The route %s could not be found.', $request->path()));
        }

        return $next($request);
    }
}
