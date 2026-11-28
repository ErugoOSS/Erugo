<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_web_login_is_throttled_after_ten_attempts(): void
    {
        User::createAccount([
            'name' => 'Test User',
            'email' => 'throttle@example.com',
            'password' => 'password',
        ], [
            'active' => true,
        ]);

        $payload = [
            'email' => 'throttle@example.com',
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/auth/login', $payload);
            $this->assertContains(
                $response->status(),
                [200, 401],
                "Attempt {$i} should return a genuine auth status, not {$response->status()}"
            );
            $this->assertNotSame(500, $response->status());
        }

        $throttled = $this->postJson('/api/auth/login', $payload);
        $throttled->assertStatus(429);
        $throttled->assertJson([
            'status' => 'error',
        ]);
        $this->assertStringContainsString('Too many attempts', $throttled->json('message'));
        $throttled->assertHeader('Retry-After');
    }

    public function test_app_login_shares_auth_login_limiter(): void
    {
        $payload = [
            'email' => 'app-throttle@example.com',
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/app/v1/auth/login', $payload);
        }

        $this->postJson('/api/app/v1/auth/login', $payload)->assertStatus(429);
    }

    public function test_forgot_password_keys_by_email_not_only_ip(): void
    {
        $email = 'forgot@example.com';

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->postJson('/api/auth/forgot-password', ['email' => $email])
                ->assertStatus(200);
        }

        // Same email from a different IP should still be blocked (email key).
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/auth/forgot-password', ['email' => $email])
            ->assertStatus(429);

        // Different emails from one IP stay under the per-email budget.
        Cache::flush();

        foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $differentEmail) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
                ->postJson('/api/auth/forgot-password', ['email' => $differentEmail])
                ->assertStatus(200);
        }
    }
}
