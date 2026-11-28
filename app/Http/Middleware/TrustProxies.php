<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

class TrustProxies extends Middleware
{
    /**
     * Cache key holding details of the most recent request that carried
     * forwarded-IP headers from an untrusted peer.
     */
    public const MISCONFIGURATION_CACHE_KEY = 'erugo.untrusted_forwarded_headers';

    private const MISCONFIGURATION_TTL_HOURS = 24;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function handle(Request $request, Closure $next)
    {
        return parent::handle($request, function (Request $request) use ($next) {
            $this->detectUntrustedForwardedHeaders($request);

            return $next($request);
        });
    }

    /**
     * Resolve trusted proxies from the admin-controlled system setting.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies(): array|string|null
    {
        try {
            $value = app(SettingsService::class)->get('trusted_proxies');
        } catch (\Throwable) {
            return null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Record when a request arrives with forwarded-IP headers from a peer we do not
     * trust. Rate limiting and download logs are keyed on the peer address in that
     * case, which silently degrades them, so admins are warned in the settings UI.
     *
     * Both the read and the write are skipped unless the misconfiguration is
     * actually present, so a correctly configured install pays nothing here.
     */
    private function detectUntrustedForwardedHeaders(Request $request): void
    {
        if (!$request->hasHeader('X-Forwarded-For') && !$request->hasHeader('Forwarded')) {
            return;
        }

        $peer = $request->server->get('REMOTE_ADDR');

        if ($peer === null || $this->peerIsTrusted($peer)) {
            return;
        }

        try {
            $existing = Cache::get(self::MISCONFIGURATION_CACHE_KEY);

            // Only write when the observed peer changes, to avoid a cache write per
            // request on installs using the database cache store.
            if (is_array($existing) && ($existing['peer'] ?? null) === $peer) {
                return;
            }

            Cache::put(self::MISCONFIGURATION_CACHE_KEY, [
                'peer' => $peer,
                'detected_at' => now()->toIso8601String(),
            ], now()->addHours(self::MISCONFIGURATION_TTL_HOURS));
        } catch (\Throwable) {
            // Detection is advisory; never let it break the request.
        }
    }

    private function peerIsTrusted(string $peer): bool
    {
        $trusted = Request::getTrustedProxies();

        if ($trusted === []) {
            return false;
        }

        return IpUtils::checkIp($peer, $trusted);
    }
}
