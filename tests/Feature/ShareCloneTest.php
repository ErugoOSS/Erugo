<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Models\Share;
use App\Models\File;
use App\Jobs\CreateShareZip;
use Tests\TestCase;
use Illuminate\Support\Str;

class ShareCloneTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeUser(bool $admin = false): User
    {
        return User::factory()->create(['admin' => $admin]);
    }

    /**
     * Create a Share with real physical files on disk so the clone can hard-link them.
     */
    private function makeShareWithFiles(User $owner, int $fileCount = 1, array $attrs = []): Share
    {
        $longId    = 'share-' . Str::random(8);
        $sharePath = $owner->id . '/' . $longId;

        $share = Share::create(array_merge([
            'user_id'        => $owner->id,
            'name'           => 'Test Share',
            'description'    => '',
            'path'           => $sharePath,
            'long_id'        => $longId,
            'size'           => 512 * $fileCount,
            'file_count'     => $fileCount,
            'download_limit' => null,
            'download_count' => 0,
            'require_email'  => false,
            'expires_at'     => now()->addDays(30),
            'status'         => 'ready',
        ], $attrs));

        $shareDir = storage_path('app/shares/' . $sharePath);
        if (!is_dir($shareDir)) {
            mkdir($shareDir, 0755, true);
        }

        for ($i = 0; $i < $fileCount; $i++) {
            $filename  = "file{$i}.txt";
            $storageId = Str::uuid()->toString();

            file_put_contents($shareDir . '/' . $filename, str_repeat('x', 512));

            File::create([
                'name'          => $filename,
                'original_name' => $filename,
                'size'          => 512,
                'type'          => 'text/plain',
                'share_id'      => $share->id,
                'temp_path'     => null,
                'full_path'     => null,
                'storage_id'    => $storageId,
            ]);
        }

        return $share;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Auth & access
    // ──────────────────────────────────────────────────────────────────────────

    public function test_clone_requires_auth(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner);

        $response = $this->postJson("/api/shares/{$share->id}/clone");
        $this->assertNotEquals(200, $response->status(), 'Unauthenticated request must not succeed');
    }

    public function test_clone_non_owner_is_rejected(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $share = $this->makeShareWithFiles($owner);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(401);
    }

    public function test_clone_admin_can_clone_any_share(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $admin = $this->makeUser(admin: true);
        $share = $this->makeShareWithFiles($owner);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(200);
    }

    public function test_clone_deleted_share_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 1, ['status' => 'deleted']);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(422);
    }

    public function test_clone_nonexistent_share_returns_404(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/shares/999999/clone')
            ->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Success path
    // ──────────────────────────────────────────────────────────────────────────

    public function test_clone_creates_new_share_with_same_file_count(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 3);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $cloneId = $response->json('data.share.id');
        $clone   = Share::find($cloneId);

        $this->assertNotNull($clone);
        $this->assertNotEquals($share->id, $clone->id);
        $this->assertEquals(3, $clone->files()->count());
        $this->assertEquals($share->size, $clone->size);
    }

    public function test_clone_preserves_storage_id(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 2);

        $originalStorageIds = $share->files()->pluck('storage_id')->sort()->values();

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(200);

        $cloneId         = $response->json('data.share.id');
        $cloneStorageIds = File::where('share_id', $cloneId)->pluck('storage_id')->sort()->values();

        $this->assertEquals($originalStorageIds->toArray(), $cloneStorageIds->toArray());
    }

    public function test_clone_uses_custom_name(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone", ['name' => 'My Custom Clone'])
            ->assertStatus(200);

        $this->assertEquals('My Custom Clone', $response->json('data.share.name'));
    }

    public function test_clone_defaults_to_clone_of_name(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(200);

        $this->assertEquals('Clone of ' . $share->name, $response->json('data.share.name'));
    }

    public function test_clone_file_exists_in_clone_directory(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 1);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(200);

        $cloneId   = $response->json('data.share.id');
        $clone     = Share::find($cloneId);
        $cloneFile = $clone->files()->first();

        $cloneFilePath = storage_path('app/shares/' . $clone->path . '/' . $cloneFile->name);
        $this->assertFileExists($cloneFilePath);
    }

    public function test_clone_dispatches_zip_job(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 2);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(200);

        Queue::assertPushed(CreateShareZip::class);
    }

    public function test_original_unaffected_after_clone(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 2);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals('ready', $share->status);
        $this->assertEquals(2, $share->files()->count());

        foreach ($share->files as $file) {
            $path = storage_path('app/shares/' . $share->path . '/' . $file->name);
            $this->assertFileExists($path);
        }
    }

    public function test_clone_has_distinct_long_id_and_path(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(200);

        $cloneLongId = $response->json('data.share.long_id');
        $this->assertNotEquals($share->long_id, $cloneLongId);
    }
}
