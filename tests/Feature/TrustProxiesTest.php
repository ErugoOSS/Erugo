<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Route::get('/_test/client-ip', fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
        ]));
    }

    private function setTrustedProxies(string $value): void
    {
        Setting::where('key', 'trusted_proxies')->update(['value' => $value]);
        app(SettingsService::class)->clearCache();
    }

    private function fromPeer(string $peer, array $headers = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $peer])
            ->withHeaders($headers)
            ->getJson('/_test/client-ip');
    }

    public function test_default_trusts_only_loopback(): void
    {
        $this->assertSame('127.0.0.1,::1', Setting::where('key', 'trusted_proxies')->value('value'));
    }

    public function test_lan_client_cannot_spoof_its_address_by_default(): void
    {
        $this->fromPeer('192.168.1.50', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->assertJson(['ip' => '192.168.1.50', 'secure' => false]);
    }

    public function test_listed_proxy_passes_on_the_real_client_ip(): void
    {
        $this->setTrustedProxies('127.0.0.1,::1,10.0.0.5');

        // Caddy appends the peer it saw, so the chain ends with the proxy itself.
        $this->fromPeer('10.0.0.5', ['X-Forwarded-For' => '203.0.113.9, 10.0.0.5', 'X-Forwarded-Proto' => 'https'])
            ->assertJson(['ip' => '203.0.113.9', 'secure' => true]);
    }

    public function test_address_not_listed_is_still_ignored(): void
    {
        $this->setTrustedProxies('127.0.0.1,::1,10.0.0.5');

        $this->fromPeer('10.0.0.6', ['X-Forwarded-For' => '203.0.113.9'])
            ->assertJson(['ip' => '10.0.0.6']);
    }

    public function test_migration_moves_nothing_once_set(): void
    {
        $this->setTrustedProxies('10.0.0.5');

        $migration = require database_path('migrations/2026_08_08_000000_add_trusted_proxies_setting.php');
        $migration->up();

        $this->assertSame('10.0.0.5', Setting::where('key', 'trusted_proxies')->value('value'));
    }
}
