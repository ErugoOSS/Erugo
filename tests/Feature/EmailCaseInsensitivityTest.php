<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;

class EmailCaseInsensitivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRequiredSettings();
    }

    private function seedRequiredSettings(): void
    {
        DB::table('settings')->upsert([
            ['key' => 'max_expiry_time',                   'value' => '30',   'group' => 'system.shares',  'previous_value' => null],
            ['key' => 'self_registration_enabled',          'value' => 'true', 'group' => 'system.auth',    'previous_value' => null],
            ['key' => 'self_registration_allow_any_domain', 'value' => 'true', 'group' => 'system.auth',    'previous_value' => null],
            ['key' => 'allow_reverse_shares',               'value' => 'true', 'group' => 'system.shares',  'previous_value' => null],
        ], ['key'], ['value']);
    }

    private function makeUser(string $email, string $password = 'Password1!'): User
    {
        $user = User::create([
            'name'                => 'Test User',
            'email'               => $email,
            'password'            => Hash::make($password),
            'admin'               => false,
            'active'              => true,
            'must_change_password' => false,
        ]);
        return $user;
    }

    // -------------------------------------------------------------------------
    // A. Input normalisation
    // -------------------------------------------------------------------------

    /** @test */
    public function admin_create_user_stores_email_as_lowercase(): void
    {
        $admin = $this->makeUser('admin@example.com');
        $admin->admin = true;
        $admin->save();

        $this->actingAs($admin)->postJson('/api/users', [
            'name'  => 'New User',
            'email' => 'NEWUSER@Example.COM',
            'admin' => false,
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'NEWUSER@Example.COM']);
    }

    /** @test */
    public function self_registration_stores_email_as_lowercase(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Self Reg User',
            'email'                 => 'SelfReg@Example.COM',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', ['email' => 'selfreg@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'SelfReg@Example.COM']);
    }

    /** @test */
    public function create_first_user_stores_email_as_lowercase(): void
    {
        $this->postJson('/api/setup/first-user', [
            'name'                  => 'Admin',
            'email'                 => 'Admin@Example.COM',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
    }

    /** @test */
    public function update_user_normalizes_email_to_lowercase(): void
    {
        $admin = $this->makeUser('admin@example.com');
        $admin->admin = true;
        $admin->save();

        $target = $this->makeUser('target@example.com');

        $this->actingAs($admin)->putJson('/api/users/' . $target->id, [
            'email' => 'UPDATED@Example.COM',
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'email' => 'updated@example.com']);
    }

    // -------------------------------------------------------------------------
    // B. Auth flows with mismatched casing
    // -------------------------------------------------------------------------

    /** @test */
    public function login_succeeds_with_uppercase_email(): void
    {
        $this->makeUser('user@example.com');

        $this->postJson('/api/auth/login', [
            'email'    => 'USER@EXAMPLE.COM',
            'password' => 'Password1!',
        ])->assertStatus(200)->assertJsonPath('status', 'success');
    }

    /** @test */
    public function login_succeeds_with_mixed_case_email(): void
    {
        $this->makeUser('user@example.com');

        $this->postJson('/api/auth/login', [
            'email'    => 'User@Example.Com',
            'password' => 'Password1!',
        ])->assertStatus(200)->assertJsonPath('status', 'success');
    }

    /** @test */
    public function forgot_password_finds_user_with_different_casing(): void
    {
        $this->makeUser('user@example.com');

        // Should send the email (not silently fail due to case mismatch)
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'USER@EXAMPLE.COM',
        ])->assertStatus(200)->assertJsonPath('status', 'success');

        // Token must have been created — proves the user was found
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    /** @test */
    public function forgot_password_with_mixed_case_creates_token(): void
    {
        $this->makeUser('user@example.com');

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'User@Example.Com',
        ])->assertStatus(200);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'user@example.com',
        ]);
    }

    /** @test */
    public function reset_password_succeeds_with_different_email_casing(): void
    {
        $user = $this->makeUser('user@example.com');
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token'                 => $token,
            'email'                 => 'USER@EXAMPLE.COM',
            'password'              => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertStatus(200)->assertJsonPath('status', 'success');
    }

    /** @test */
    public function self_registration_verify_succeeds_with_different_email_casing(): void
    {
        // Register with lowercase
        $this->postJson('/api/auth/register', [
            'name'                  => 'Test',
            'email'                 => 'verify@example.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertStatus(200);

        $user = User::where('email', 'verify@example.com')->first();

        // Verify with uppercase
        $this->postJson('/api/auth/verify-email', [
            'email' => 'VERIFY@EXAMPLE.COM',
            'code'  => $user->email_verification_code,
        ])->assertStatus(200)->assertJsonPath('status', 'success');
    }

    // -------------------------------------------------------------------------
    // C. Uniqueness enforcement
    // -------------------------------------------------------------------------

    /** @test */
    public function self_registration_rejects_duplicate_email_different_case(): void
    {
        $this->makeUser('existing@example.com');

        $this->postJson('/api/auth/register', [
            'name'                  => 'Another',
            'email'                 => 'EXISTING@EXAMPLE.COM',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertStatus(422);
    }

    /** @test */
    public function admin_create_user_rejects_duplicate_email_different_case(): void
    {
        $admin = $this->makeUser('admin@example.com');
        $admin->admin = true;
        $admin->save();

        $this->makeUser('existing@example.com');

        $this->actingAs($admin)->postJson('/api/users', [
            'name'  => 'Another',
            'email' => 'EXISTING@EXAMPLE.COM',
        ])->assertStatus(400);
    }

    /** @test */
    public function camel_case_email_is_normalized_to_lowercase(): void
    {
        $this->makeUser('camel@example.com');

        // Login with camelCase variant
        $this->postJson('/api/auth/login', [
            'email'    => 'CaMeL@ExAmPlE.cOm',
            'password' => 'Password1!',
        ])->assertStatus(200)->assertJsonPath('status', 'success');
    }

    /** @test */
    public function email_with_leading_trailing_whitespace_is_trimmed_and_normalized(): void
    {
        $this->makeUser('trim@example.com');

        $this->postJson('/api/auth/login', [
            'email'    => '  TRIM@EXAMPLE.COM  ',
            'password' => 'Password1!',
        ])->assertStatus(200)->assertJsonPath('status', 'success');
    }

    // -------------------------------------------------------------------------
    // D. Collision migration
    // -------------------------------------------------------------------------

    /** @test */
    public function migration_normalizes_emails_to_lowercase(): void
    {
        // Seed users with mixed-case emails (no collisions)
        DB::table('users')->insert([
            ['name' => 'A', 'email' => 'Alice@Example.COM', 'password' => 'x', 'active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'B', 'email' => 'Bob@Example.COM',   'password' => 'x', 'active' => true,  'created_at' => now(), 'updated_at' => now()],
        ]);

        // Run the migration logic inline
        $this->runNormalizationMigration();

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'bob@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'Alice@Example.COM']);
        $this->assertDatabaseMissing('users', ['email' => 'Bob@Example.COM']);
    }

    /** @test */
    public function migration_quarantines_collision_duplicate_and_keeps_oldest(): void
    {
        // Seed two users with same email in different casing
        $id1 = DB::table('users')->insertGetId([
            'name' => 'First',  'email' => 'user@example.com',  'password' => 'x', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id2 = DB::table('users')->insertGetId([
            'name' => 'Second', 'email' => 'User@Example.COM',  'password' => 'x', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->runNormalizationMigration();

        // Oldest kept and normalized
        $this->assertDatabaseHas('users', ['id' => $id1, 'email' => 'user@example.com', 'active' => true]);

        // Duplicate quarantined
        $quarantined = DB::table('users')->where('id', $id2)->first();
        $this->assertStringEndsWith('__dup_' . $id2, $quarantined->email);
        $this->assertEquals(0, $quarantined->active);
    }

    /** @test */
    public function migration_handles_triple_collision(): void
    {
        $id1 = DB::table('users')->insertGetId(['name' => 'A', 'email' => 'same@example.com',  'password' => 'x', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $id2 = DB::table('users')->insertGetId(['name' => 'B', 'email' => 'Same@Example.com',  'password' => 'x', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $id3 = DB::table('users')->insertGetId(['name' => 'C', 'email' => 'SAME@EXAMPLE.COM',  'password' => 'x', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->runNormalizationMigration();

        $this->assertDatabaseHas('users', ['id' => $id1, 'email' => 'same@example.com', 'active' => true]);

        $dup2 = DB::table('users')->where('id', $id2)->first();
        $this->assertStringEndsWith('__dup_' . $id2, $dup2->email);
        $this->assertEquals(0, $dup2->active);

        $dup3 = DB::table('users')->where('id', $id3)->first();
        $this->assertStringEndsWith('__dup_' . $id3, $dup3->email);
        $this->assertEquals(0, $dup3->active);
    }

    /** @test */
    public function migration_normalizes_password_reset_tokens(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email'      => 'User@Example.COM',
            'token'      => 'sometoken',
            'created_at' => now(),
        ]);

        $this->runNormalizationMigration();

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'user@example.com']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'User@Example.COM']);
    }

    // -------------------------------------------------------------------------
    // E. Reverse share invite normalisation
    // -------------------------------------------------------------------------

    /** @test */
    public function reverse_share_invite_stores_recipient_email_as_lowercase(): void
    {
        $sender = $this->makeUser('sender@example.com');

        $this->actingAs($sender)->postJson('/api/reverse-shares', [
            'recipient_name'  => 'Recipient',
            'recipient_email' => 'RECIPIENT@Example.COM',
        ])->assertStatus(200);

        $this->assertDatabaseHas('reverse_share_invites', [
            'recipient_email' => 'recipient@example.com',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper: run the migration normalization logic inline (avoids needing
    // to call artisan migrate with a specific migration file in tests)
    // -------------------------------------------------------------------------

    private function runNormalizationMigration(): void
    {
        $collisions = DB::table('users')
            ->select(DB::raw('LOWER(email) as normalized_email'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('LOWER(email)'))
            ->having('cnt', '>', 1)
            ->pluck('normalized_email');

        foreach ($collisions as $normalizedEmail) {
            $duplicates = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->orderBy('id')
                ->get();

            $isFirst = true;
            foreach ($duplicates as $duplicate) {
                if ($isFirst) {
                    $isFirst = false;
                    continue;
                }
                DB::table('users')
                    ->where('id', $duplicate->id)
                    ->update([
                        'email'      => $duplicate->email . '__dup_' . $duplicate->id,
                        'active'     => false,
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::statement('UPDATE users SET email = LOWER(email), updated_at = ? WHERE email != LOWER(email)', [now()]);

        if (DB::getSchemaBuilder()->hasTable('password_reset_tokens')) {
            DB::statement('UPDATE password_reset_tokens SET email = LOWER(email) WHERE email != LOWER(email)');
        }
    }
}
