<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Share;
use Tests\TestCase;

class SharePendingDeletionTest extends TestCase
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

    private function requestDeletionUrl(int $id): string
    {
        return "/api/shares/{$id}/request-deletion";
    }

    private function undoDeletionUrl(int $id): string
    {
        return "/api/shares/{$id}/undo-deletion";
    }

    // ──────────────────────────────────────────────────────────────────────────
    // requestDeletion — authentication
    // ──────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_deletion_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $response = $this->postJson($this->requestDeletionUrl($share->id));

        $this->assertNotEquals(200, $response->status());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // requestDeletion — authorisation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_owner_can_request_deletion_of_own_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->requestDeletionUrl($share->id));

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $share->refresh();
        $this->assertEquals('pending_deletion', $share->status);
    }

    public function test_admin_can_request_deletion_of_any_share(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser(admin: true);
        $share = $this->makeShare($owner);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson($this->requestDeletionUrl($share->id));

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $share->refresh();
        $this->assertEquals('pending_deletion', $share->status);
        $this->assertEquals('admin', $share->deletion_requested_by);
    }

    public function test_non_owner_cannot_request_deletion(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $share = $this->makeShare($owner);

        $response = $this->actingAs($other, 'sanctum')
            ->postJson($this->requestDeletionUrl($share->id));

        $response->assertStatus(401);

        $share->refresh();
        $this->assertEquals('ready', $share->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // requestDeletion — state recorded correctly
    // ──────────────────────────────────────────────────────────────────────────

    public function test_request_deletion_sets_deletion_requested_by_user(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->requestDeletionUrl($share->id));

        $share->refresh();
        $this->assertEquals('user', $share->deletion_requested_by);
        $this->assertNotNull($share->deletion_requested_at);
    }

    public function test_request_deletion_sets_deletion_requested_by_admin(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser(admin: true);
        $share = $this->makeShare($owner);

        $this->actingAs($admin, 'sanctum')
            ->postJson($this->requestDeletionUrl($share->id));

        $share->refresh();
        $this->assertEquals('admin', $share->deletion_requested_by);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // requestDeletion — guard: already pending / deleted
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cannot_double_request_deletion(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->requestDeletionUrl($share->id));

        $response->assertStatus(422);
    }

    public function test_cannot_request_deletion_of_already_deleted_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'deleted']);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->requestDeletionUrl($share->id));

        $response->assertStatus(422);
    }

    public function test_cannot_request_deletion_of_nonexistent_share(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/shares/999999/request-deletion');

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // undoDeletion — authentication
    // ──────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_undo_deletion_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $response = $this->postJson($this->undoDeletionUrl($share->id));

        $this->assertNotEquals(200, $response->status());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // undoDeletion — authorisation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_owner_can_undo_deletion(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, [
            'status'                 => 'pending_deletion',
            'deletion_requested_at'  => Carbon::now(),
            'deletion_requested_by'  => 'user',
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->undoDeletionUrl($share->id));

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $share->refresh();
        $this->assertEquals('ready', $share->status);
        $this->assertNull($share->deletion_requested_at);
        $this->assertNull($share->deletion_requested_by);
    }

    public function test_admin_can_undo_deletion_of_any_share(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser(admin: true);
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson($this->undoDeletionUrl($share->id));

        $response->assertStatus(200);

        $share->refresh();
        $this->assertEquals('ready', $share->status);
    }

    public function test_non_owner_cannot_undo_deletion(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'pending_deletion']);

        $response = $this->actingAs($other, 'sanctum')
            ->postJson($this->undoDeletionUrl($share->id));

        $response->assertStatus(401);

        $share->refresh();
        $this->assertEquals('pending_deletion', $share->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // undoDeletion — guard: not pending
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cannot_undo_deletion_of_ready_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'ready']);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->undoDeletionUrl($share->id));

        $response->assertStatus(422);
    }

    public function test_cannot_undo_deletion_of_deleted_share(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, ['status' => 'deleted']);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson($this->undoDeletionUrl($share->id));

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Round-trip: request → undo → request again
    // ──────────────────────────────────────────────────────────────────────────

    public function test_round_trip_request_undo_rerequest(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->requestDeletionUrl($share->id))
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals('pending_deletion', $share->status);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->undoDeletionUrl($share->id))
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals('ready', $share->status);

        $this->actingAs($owner, 'sanctum')
            ->postJson($this->requestDeletionUrl($share->id))
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals('pending_deletion', $share->status);
    }
}
