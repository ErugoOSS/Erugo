<?php

namespace Tests\Feature;

use App\Models\Share;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed();
    }

    private function makeUser(string $email, array $flags = []): User
    {
        return User::createAccount([
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
        ], $flags + ['active' => true]);
    }

    private function webToken(string $email): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk()->json('data.access_token');
    }

    private function makeShare(User $user, array $attributes = []): Share
    {
        $longId = Str::random(12);

        return Share::create($attributes + [
            'user_id' => $user->id,
            'name' => 'Test share',
            'path' => $user->id . '/' . $longId,
            'long_id' => $longId,
            'size' => 10,
            'file_count' => 1,
            'expires_at' => now()->addDays(3),
            'status' => 'ready',
        ]);
    }

    public function test_share_json_does_not_contain_password_hash(): void
    {
        $user = $this->makeUser('owner@example.com');
        $share = $this->makeShare($user, ['password' => Hash::make('share-secret')]);
        $token = $this->webToken('owner@example.com');

        // expire returns the Share model as-is, so it relies on $hidden.
        $response = $this->withToken($token)
            ->postJson("/api/shares/{$share->id}/expire")
            ->assertOk();

        $this->assertArrayNotHasKey('password', $response->json('data.share'));
        $this->assertStringNotContainsString('$2y$', $response->getContent());
        $this->assertArrayNotHasKey('password', $share->fresh()->toArray());
    }

    public function test_stats_days_parameter_is_clamped(): void
    {
        $this->makeUser('admin@example.com', ['admin' => true]);
        $token = $this->webToken('admin@example.com');

        $this->withToken($token)->getJson('/api/stats?days=999999')
            ->assertOk()
            ->assertJsonPath('data.downloads.period_days', 365);

        $this->withToken($token)->getJson('/api/stats?days=-20')
            ->assertOk()
            ->assertJsonPath('data.downloads.period_days', 1);
    }

    public function test_app_extend_days_parameter_is_clamped(): void
    {
        $user = $this->makeUser('extender@example.com');
        $share = $this->makeShare($user);
        $token = $this->webToken('extender@example.com');

        // The seeded max_expiry_time is 10 days.
        $this->withToken($token)
            ->postJson("/api/app/v1/shares/{$share->id}/extend", ['days' => 999999999])
            ->assertOk();
        $days = now()->diffInDays($share->fresh()->expires_at);
        $this->assertGreaterThan(9, $days);
        $this->assertLessThanOrEqual(10, $days);

        $this->withToken($token)
            ->postJson("/api/app/v1/shares/{$share->id}/extend", ['days' => -5])
            ->assertOk();
        $this->assertTrue($share->fresh()->expires_at->isFuture());
    }

    public function test_app_login_refuses_inactive_user(): void
    {
        $this->makeUser('inactive@example.com', ['active' => false]);

        $this->postJson('/api/app/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertStatus(403)->assertJsonPath('code', 'AUTH_ACCOUNT_INACTIVE');
    }

    public function test_app_refresh_refuses_user_deactivated_after_login(): void
    {
        $user = $this->makeUser('later@example.com');

        $refreshToken = $this->postJson('/api/app/v1/auth/login', [
            'email' => 'later@example.com',
            'password' => 'password',
        ])->assertOk()->json('data.refresh_token');

        $user->active = false;
        $user->save();

        // The JWT guard keeps the user from the login call in memory. A real
        // refresh is a new request, so start from a clean guard.
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/app/v1/auth/refresh', ['refresh_token' => $refreshToken])
            ->assertStatus(403)
            ->assertJsonPath('code', 'AUTH_ACCOUNT_INACTIVE');
    }

    public function test_refresh_cookie_is_secure_only_over_https(): void
    {
        $this->makeUser('cookie@example.com');
        $credentials = ['email' => 'cookie@example.com', 'password' => 'password'];

        $http = $this->postJson('http://localhost/api/auth/login', $credentials)->assertOk();
        $this->assertFalse($http->getCookie('refresh_token', false)->isSecure());

        $https = $this->postJson('https://localhost/api/auth/login', $credentials)->assertOk();
        $this->assertTrue($https->getCookie('refresh_token', false)->isSecure());
    }
}
