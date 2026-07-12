<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Share;
use App\Models\Setting;
use Tests\TestCase;

class ExtendShareTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeUser(bool $admin = false): User
    {
        return User::factory()->create(['admin' => $admin]);
    }

    private function makeShare(User $owner, array $attrs = []): Share
    {
        return Share::create(array_merge([
            'user_id'        => $owner->id,
            'name'           => 'Test Share',
            'description'    => '',
            'path'           => 'test-path-' . uniqid(),
            'long_id'        => 'share-' . uniqid(),
            'size'           => 0,
            'file_count'     => 0,
            'download_limit' => null,
            'download_count' => 0,
            'require_email'  => false,
            'expires_at'     => Carbon::now()->addDays(30),
            'status'         => 'active',
        ], $attrs));
    }

    private function setMaxExpiry(int $days): void
    {
        Setting::updateOrCreate(
            ['key' => 'max_expiry_time'],
            ['value' => (string) $days, 'group' => 'shares']
        );
    }

    private function extendUrl(int $shareId): string
    {
        return "/api/shares/{$shareId}/extend";
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Authentication / authorisation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $response = $this->postJson($this->extendUrl($share->id), ['amount' => 7, 'unit' => 'days']);

        // Must not succeed — JWT guard may return 401, 302, or 500 depending on config
        $this->assertNotEquals(200, $response->status(), 'Unauthenticated request must not succeed');
    }

    public function test_non_owner_cannot_extend_share(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $share   = $this->makeShare($owner);

        $response = $this->actingAs($other, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 7, 'unit' => 'days']);

        $response->assertStatus(401);
    }

    public function test_admin_can_extend_any_share(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser(admin: true);
        $share = $this->makeShare($owner);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 7, 'unit' => 'days']);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Relative extension — base-from-expiry bug regression
    // ──────────────────────────────────────────────────────────────────────────

    public function test_relative_extension_extends_from_current_expiry_not_now(): void
    {
        // Share expires 60 days from now; extending by 7 days should give ~67 days, not ~7 days
        $owner  = $this->makeUser();
        $future = Carbon::now()->addDays(60);
        $share  = $this->makeShare($owner, ['expires_at' => $future]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 7, 'unit' => 'days'])
            ->assertStatus(200);

        $share->refresh();
        $expected = $future->copy()->addDays(7);

        // Allow 60-second tolerance for test execution time
        $this->assertEqualsWithDelta(
            $expected->timestamp,
            $share->expires_at->timestamp,
            60,
            'Extension should be based on current expiry, not now()'
        );
    }

    public function test_relative_extension_uses_now_as_base_when_share_is_expired(): void
    {
        $owner = $this->makeUser();
        $past  = Carbon::now()->subDays(5);
        $share = $this->makeShare($owner, ['expires_at' => $past]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 7, 'unit' => 'days'])
            ->assertStatus(200);

        $share->refresh();
        $expected = Carbon::now()->addDays(7);

        $this->assertEqualsWithDelta(
            $expected->timestamp,
            $share->expires_at->timestamp,
            60,
            'Expired share extension should be based on now()'
        );
    }

    public function test_extend_by_weeks(): void
    {
        $owner = $this->makeUser();
        $base  = Carbon::now()->addDays(10);
        $share = $this->makeShare($owner, ['expires_at' => $base]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 2, 'unit' => 'weeks'])
            ->assertStatus(200);

        $share->refresh();
        $expected = $base->copy()->addWeeks(2);

        $this->assertEqualsWithDelta($expected->timestamp, $share->expires_at->timestamp, 60);
    }

    public function test_extend_by_months(): void
    {
        $owner = $this->makeUser();
        $base  = Carbon::now()->addDays(10);
        $share = $this->makeShare($owner, ['expires_at' => $base]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 1, 'unit' => 'months'])
            ->assertStatus(200);

        $share->refresh();
        $expected = $base->copy()->addMonths(1);

        $this->assertEqualsWithDelta($expected->timestamp, $share->expires_at->timestamp, 60);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Null expires_at (unlimited) — bug regression
    // ──────────────────────────────────────────────────────────────────────────

    public function test_extending_null_expiry_share_uses_now_as_base(): void
    {
        // Admin-set unlimited share (expires_at = null); extending should not crash
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['expires_at' => null]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 7, 'unit' => 'days'])
            ->assertStatus(200);

        $share->refresh();
        $expected = Carbon::now()->addDays(7);

        $this->assertEqualsWithDelta($expected->timestamp, $share->expires_at->timestamp, 60);
    }

    public function test_share_with_null_expiry_expired_attribute_is_false(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['expires_at' => null]);

        $this->assertFalse($share->expired, 'Unlimited share must not be treated as expired');
    }

    public function test_share_with_null_expiry_deletes_at_is_null(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['expires_at' => null]);

        $this->assertNull($share->deletes_at, 'Unlimited share must not have a deletes_at date');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // max_expiry_time enforcement
    // ──────────────────────────────────────────────────────────────────────────

    public function test_extension_is_rejected_when_it_exceeds_max_expiry(): void
    {
        $this->setMaxExpiry(30);

        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['expires_at' => Carbon::now()->addDays(28)]);

        // 28 (current) + 7 = 35 days from now > 30 day max
        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 7, 'unit' => 'days']);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_extension_within_max_expiry_is_accepted(): void
    {
        $this->setMaxExpiry(30);

        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['expires_at' => Carbon::now()->addDays(20)]);

        // 20 (current) + 5 = 25 days from now ≤ 30 day max
        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 5, 'unit' => 'days'])
            ->assertStatus(200);
    }

    public function test_admin_bypasses_max_expiry_on_relative_extension(): void
    {
        $this->setMaxExpiry(30);

        $owner = $this->makeUser();
        $admin = $this->makeUser(admin: true);
        $share = $this->makeShare($owner, ['expires_at' => Carbon::now()->addDays(28)]);

        // Would exceed 30 days for a regular user, but admin should be allowed
        $this->actingAs($admin, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 30, 'unit' => 'days'])
            ->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Admin-only options
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_can_set_unlimited_expiry(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser(admin: true);
        $share = $this->makeShare($owner);

        $this->actingAs($admin, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['unlimited' => true])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $share->refresh();
        $this->assertNull($share->expires_at, 'Admin unlimited should set expires_at to null');
    }

    public function test_admin_can_set_specific_date(): void
    {
        $owner  = $this->makeUser();
        $admin  = $this->makeUser(admin: true);
        $share  = $this->makeShare($owner);
        $target = Carbon::now()->addDays(90)->toDateString();

        $this->actingAs($admin, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['expires_at' => $target])
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals($target, $share->expires_at->toDateString());
    }

    public function test_regular_user_cannot_set_unlimited(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        // Non-admin sending unlimited=true should fall through to relative logic
        // and use default 7-day relative extension (not set null)
        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['unlimited' => true])
            ->assertStatus(200);

        $share->refresh();
        $this->assertNotNull($share->expires_at, 'Non-admin unlimited flag must be ignored');
    }

    public function test_regular_user_can_set_specific_date_within_max_expiry(): void
    {
        $this->setMaxExpiry(30);

        $owner  = $this->makeUser();
        $share  = $this->makeShare($owner);
        $target = Carbon::now()->addDays(20)->toDateString(); // within 30-day limit

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['expires_at' => $target])
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals($target, $share->expires_at->toDateString());
    }

    public function test_regular_user_date_picker_rejects_date_beyond_max_expiry(): void
    {
        $this->setMaxExpiry(30);

        $owner  = $this->makeUser();
        $share  = $this->makeShare($owner);
        $target = Carbon::now()->addDays(60)->toDateString(); // exceeds 30-day limit

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['expires_at' => $target])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Input validation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_invalid_unit_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 7, 'unit' => 'years'])
            ->assertStatus(422);
    }

    public function test_zero_amount_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 0, 'unit' => 'days'])
            ->assertStatus(422);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => -5, 'unit' => 'days'])
            ->assertStatus(422);
    }

    public function test_nonexistent_share_returns_404(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/shares/999999/extend', ['amount' => 7, 'unit' => 'days'])
            ->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Default behaviour (no payload — backward compat with old API)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_no_payload_defaults_to_7_day_relative_extension(): void
    {
        $owner = $this->makeUser();
        $base  = Carbon::now()->addDays(10);
        $share = $this->makeShare($owner, ['expires_at' => $base]);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), [])
            ->assertStatus(200);

        $share->refresh();
        $expected = $base->copy()->addDays(7);

        $this->assertEqualsWithDelta($expected->timestamp, $share->expires_at->timestamp, 60);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Response shape
    // ──────────────────────────────────────────────────────────────────────────

    public function test_success_response_includes_updated_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->extendUrl($share->id), ['amount' => 7, 'unit' => 'days']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['share' => ['id', 'expires_at']],
            ]);
    }
}
