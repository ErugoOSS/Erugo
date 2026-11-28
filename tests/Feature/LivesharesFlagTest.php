<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class LivesharesFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeds liveshares_enabled = 'true', so these tests show the env flag wins.
        $this->seed();

        $this->admin = User::createAccount([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
        ], [
            'admin' => true,
            'active' => true,
        ]);
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($this->admin)];
    }

    public static function apiRoutes(): array
    {
        return [
            'web api: list' => ['GET', '/api/liveshares'],
            'web api: create' => ['POST', '/api/liveshares'],
            'web api: show' => ['GET', '/api/liveshares/some-long-id'],
            'web api: admin list' => ['GET', '/api/liveshares/admin/all'],
            'web api: admin limits' => ['PUT', '/api/liveshares/admin/1/limits'],
            'web api: invite info' => ['GET', '/api/liveshares/invite/some-token'],
            'web api: invite register' => ['POST', '/api/liveshares/invite/some-token/register'],
            'web api: invite accept' => ['POST', '/api/liveshares/invite/some-token/accept'],
            'web api: file download' => ['GET', '/api/liveshares/some-long-id/files/1/download'],
            'web api: thumb' => ['GET', '/api/liveshares/some-long-id/files/1/thumb'],
            'web api: avatar' => ['GET', '/api/liveshares/some-long-id/avatar'],
            'app api: list' => ['GET', '/api/app/v1/liveshares'],
            'app api: create' => ['POST', '/api/app/v1/liveshares'],
            'app api: members' => ['GET', '/api/app/v1/liveshares/some-long-id/members'],
            'app api: admin list' => ['GET', '/api/app/v1/liveshares/admin/all'],
            'app api: invite info' => ['GET', '/api/app/v1/liveshares/invite/some-token'],
            'app api: invite register' => ['POST', '/api/app/v1/liveshares/invite/some-token/register'],
            'app api: invite accept' => ['POST', '/api/app/v1/liveshares/invite/some-token/accept'],
            'app api: avatar' => ['GET', '/api/app/v1/liveshares/some-long-id/avatar'],
        ];
    }

    #[DataProvider('apiRoutes')]
    public function test_api_routes_404_for_guests_and_admins_when_flag_is_off(string $method, string $uri): void
    {
        config()->set('erugo.liveshares', false);

        // Guest first: the JWT guard keeps the admin for the rest of the test.
        $this->json($method, $uri)->assertNotFound();
        $this->json($method, $uri, [], $this->adminHeaders())->assertNotFound();
    }

    public function test_disabled_route_looks_like_a_missing_route(): void
    {
        config()->set('erugo.liveshares', false);

        $missing = $this->getJson('/api/liveshare/some-long-id', $this->adminHeaders());
        $disabled = $this->getJson('/api/liveshares/some-long-id', $this->adminHeaders());

        $missing->assertNotFound();
        $disabled->assertNotFound();
        $this->assertSame(
            ['message' => 'The route api/liveshare/some-long-id could not be found.'],
            $missing->json()
        );
        $this->assertSame(
            ['message' => 'The route api/liveshares/some-long-id could not be found.'],
            $disabled->json()
        );
    }

    public function test_web_pages_404_when_flag_is_off(): void
    {
        config()->set('erugo.liveshares', false);

        $this->get('/liveshares/some-long-id')->assertNotFound();

        // The gate runs before the session starts, so no cookies, just like a missing route.
        $this->get('/liveshares/invite/some-token')
            ->assertNotFound()
            ->assertCookieMissing(config('session.cookie'));
    }

    public function test_home_page_tells_frontend_liveshares_are_off(): void
    {
        config()->set('erugo.liveshares', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('&quot;liveshares_available&quot;:false', false);
    }

    public function test_app_config_reports_liveshares_disabled_when_flag_is_off(): void
    {
        config()->set('erugo.liveshares', false);

        $this->getJson('/api/app/v1/config')
            ->assertOk()
            ->assertJsonPath('data.features.liveshares_enabled', false);
    }

    public function test_flag_is_off_by_default(): void
    {
        $this->assertFalse(config('erugo.liveshares'));
        $this->getJson('/api/liveshares', $this->adminHeaders())->assertNotFound();
    }

    public function test_routes_work_when_flag_is_on(): void
    {
        config()->set('erugo.liveshares', true);

        // Guests now get past the gate and are stopped by auth. Done first
        // because the JWT guard keeps the admin for the rest of the test.
        $this->getJson('/api/liveshares')->assertUnauthorized();

        $this->getJson('/api/liveshares', $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'success');
        $this->getJson('/api/liveshares/admin/all', $this->adminHeaders())->assertOk();
        $this->getJson('/api/app/v1/liveshares', $this->adminHeaders())->assertOk();
        $this->get('/liveshares/some-long-id')->assertOk();
    }

    public function test_setting_can_still_switch_liveshares_off_when_flag_is_on(): void
    {
        config()->set('erugo.liveshares', true);
        Setting::where('key', 'liveshares_enabled')->update(['value' => 'false']);
        app(SettingsService::class)->clearCache();

        $this->getJson('/api/app/v1/config')
            ->assertOk()
            ->assertJsonPath('data.features.liveshares_enabled', false);
    }

    public function test_app_config_follows_setting_when_flag_is_on(): void
    {
        config()->set('erugo.liveshares', true);

        $this->getJson('/api/app/v1/config')
            ->assertOk()
            ->assertJsonPath('data.features.liveshares_enabled', true);
    }
}
