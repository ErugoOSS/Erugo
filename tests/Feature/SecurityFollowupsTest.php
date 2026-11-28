<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SecurityFollowupsTest extends TestCase
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

    private function jwtPayload(string $jwt): array
    {
        $segment = explode('.', $jwt)[1];

        return json_decode(base64_decode(strtr($segment, '-_', '+/')), true);
    }

    public function test_web_refresh_token_and_cookie_last_24_hours(): void
    {
        $this->makeUser('web@example.com');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'web@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame(config('jwt.ttl') * 60, $response->json('data.expires_in'));

        $cookie = $response->getCookie('refresh_token', false);
        $payload = $this->jwtPayload($cookie->getValue());
        $this->assertSame(24 * 60 * 60, $payload['exp'] - $payload['iat']);
        $this->assertEqualsWithDelta(time() + 24 * 60 * 60, $cookie->getExpiresTime(), 5);
    }

    public function test_app_refresh_token_lasts_30_days(): void
    {
        $this->makeUser('app@example.com');

        $response = $this->postJson('/api/app/v1/auth/login', [
            'email' => 'app@example.com',
            'password' => 'password',
        ])->assertOk();

        $payload = $this->jwtPayload($response->json('data.refresh_token'));
        $this->assertSame(30 * 24 * 60 * 60, $payload['exp'] - $payload['iat']);
        $this->assertSame(30 * 24 * 60 * 60, $response->json('data.refresh_token_expires_in'));

        $access = $this->jwtPayload($response->json('data.access_token'));
        $this->assertSame(config('jwt.ttl') * 60, $access['exp'] - $access['iat']);
        $this->assertSame(config('jwt.ttl') * 60, $response->json('data.access_token_expires_in'));
    }

    public function test_reverse_share_accept_rejects_malformed_token(): void
    {
        $this->postJson('/api/reverse-shares/accept', ['token' => 'not-a-real-token'])
            ->assertStatus(401)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/app/v1/reverse-shares/accept', ['token' => 'not-a-real-token'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_TOKEN');
    }

    public function test_password_reset_gives_inactive_account_no_refresh_cookie(): void
    {
        $user = $this->makeUser('inactive@example.com', ['active' => false]);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'inactive@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(403)->assertJsonPath('message', 'Account is not active');
        $this->assertNull($response->getCookie('refresh_token', false));
    }

    public function test_password_reset_gives_active_account_a_refresh_cookie(): void
    {
        $user = $this->makeUser('active@example.com');
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'active@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->getCookie('refresh_token', false));
    }
}
