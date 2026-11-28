<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Share;
use App\Models\Setting;
use Tests\TestCase;

class AdminShareDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable deletion emails — sendEmail uses a custom SMTP mailer that
        // requires live DB settings; suppressing it keeps tests self-contained.
        Setting::updateOrCreate(
            ['key' => 'emails_share_deleted_enabled'],
            ['value' => '0', 'group' => 'emails']
        );
    }

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
            'path'           => 'test/' . $owner->id . '/' . uniqid(),
            'long_id'        => 'share-' . uniqid(),
            'size'           => 0,
            'file_count'     => 0,
            'download_limit' => null,
            'download_count' => 0,
            'require_email'  => false,
            'expires_at'     => Carbon::now()->addDays(30),
            'status'         => 'ready',
        ], $attrs));
    }

    private function deleteImmediatelyUrl(int $id): string
    {
        return "/api/shares/{$id}/delete-immediately";
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Authentication / authorisation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $response = $this->postJson($this->deleteImmediatelyUrl($share->id), [
            'confirmation' => 'DELETE',
        ]);

        $this->assertNotEquals(200, $response->status());
    }

    public function test_non_admin_cannot_delete_immediately(): void
    {
        $owner = $this->makeUser(admin: false);
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), [
                'confirmation' => 'DELETE',
            ]);

        $response->assertStatus(403);

        $share->refresh();
        $this->assertEquals('pending_deletion', $share->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Confirmation phrase
    // ──────────────────────────────────────────────────────────────────────────

    public function test_missing_confirmation_is_rejected(): void
    {
        $admin = $this->makeUser(admin: true);
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), []);

        $response->assertStatus(422);
    }

    public function test_wrong_confirmation_phrase_is_rejected(): void
    {
        $admin = $this->makeUser(admin: true);
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        foreach (['delete', 'yes', 'confirm', 'delet'] as $bad) {
            $response = $this->actingAs($admin, 'sanctum')
                ->postJson($this->deleteImmediatelyUrl($share->id), [
                    'confirmation' => $bad,
                ]);

            $response->assertStatus(422, "Expected 422 for confirmation: '{$bad}'");
        }
    }

    public function test_correct_uppercase_delete_confirmation_is_accepted(): void
    {
        $admin = $this->makeUser(admin: true);
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), [
                'confirmation' => 'DELETE',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Eligible states
    // ──────────────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_pending_deletion_share_immediately(): void
    {
        $admin = $this->makeUser(admin: true);
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $this->actingAs($admin, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), [
                'confirmation' => 'DELETE',
            ])
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals('deleted', $share->status);
    }

    public function test_admin_can_delete_expired_share_immediately(): void
    {
        $admin = $this->makeUser(admin: true);
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, [
            'status'     => 'ready',
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), [
                'confirmation' => 'DELETE',
            ])
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals('deleted', $share->status);
    }

    public function test_admin_cannot_delete_active_share_immediately(): void
    {
        $admin = $this->makeUser(admin: true);
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, [
            'status'     => 'ready',
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), [
                'confirmation' => 'DELETE',
            ]);

        $response->assertStatus(422);

        $share->refresh();
        $this->assertEquals('ready', $share->status);
    }

    public function test_admin_cannot_delete_already_deleted_share(): void
    {
        $admin = $this->makeUser(admin: true);
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'deleted']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), [
                'confirmation' => 'DELETE',
            ]);

        $response->assertStatus(422);
    }

    public function test_delete_immediately_of_nonexistent_share_returns_404(): void
    {
        $admin = $this->makeUser(admin: true);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/shares/999999/delete-immediately', [
                'confirmation' => 'DELETE',
            ]);

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Idempotency guard
    // ──────────────────────────────────────────────────────────────────────────

    public function test_double_delete_immediately_is_rejected(): void
    {
        $admin = $this->makeUser(admin: true);
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $this->actingAs($admin, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), ['confirmation' => 'DELETE'])
            ->assertStatus(200);

        // Second call: share is now 'deleted' — must be rejected
        $this->actingAs($admin, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), ['confirmation' => 'DELETE'])
            ->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Workflow: F4 request → F5 delete-immediately
    // ──────────────────────────────────────────────────────────────────────────

    public function test_full_workflow_user_requests_then_admin_deletes(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser(admin: true);
        $share = $this->makeShare($owner);

        // User marks for deletion
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/request-deletion")
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals('pending_deletion', $share->status);

        // Admin immediately deletes
        $this->actingAs($admin, 'sanctum')
            ->postJson($this->deleteImmediatelyUrl($share->id), ['confirmation' => 'DELETE'])
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals('deleted', $share->status);
    }
}
