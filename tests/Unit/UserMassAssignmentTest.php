<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserMassAssignmentTest extends TestCase
{
    public function test_fillable_only_allows_identity_fields(): void
    {
        $this->assertSame(
            ['name', 'email', 'password'],
            (new User())->getFillable()
        );
    }

    public function test_fill_ignores_privileged_fields(): void
    {
        $user = new User();
        // Omit password — the hashed cast needs the Laravel Hash facade.
        $user->fill([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'admin' => true,
            'active' => false,
            'must_change_password' => true,
            'is_guest' => true,
            'is_restricted' => true,
            'email_verification_code' => '123456',
        ]);

        $attributes = $user->getAttributes();

        $this->assertSame('Test User', $attributes['name'] ?? null);
        $this->assertSame('test@example.com', $attributes['email'] ?? null);
        $this->assertArrayNotHasKey('admin', $attributes);
        $this->assertArrayNotHasKey('active', $attributes);
        $this->assertArrayNotHasKey('must_change_password', $attributes);
        $this->assertArrayNotHasKey('is_guest', $attributes);
        $this->assertArrayNotHasKey('is_restricted', $attributes);
        $this->assertArrayNotHasKey('email_verification_code', $attributes);
    }

    public function test_create_account_rejects_unknown_flags_before_persist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported account flag: not_a_real_flag');

        User::createAccount([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret-password',
        ], [
            'not_a_real_flag' => true,
        ]);
    }
}
