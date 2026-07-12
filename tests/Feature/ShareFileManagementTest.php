<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Models\Share;
use App\Models\File;
use App\Models\UploadSession;
use Tests\TestCase;
use Illuminate\Support\Str;

class ShareFileManagementTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeUser(bool $admin = false): User
    {
        return User::factory()->create(['admin' => $admin]);
    }

    private function makeShare(User $owner, int $fileCount = 1, array $attrs = []): Share
    {
        $longId = 'share-' . Str::random(8);
        $share = Share::create(array_merge([
            'user_id'        => $owner->id,
            'name'           => 'Test Share',
            'description'    => '',
            'path'           => $owner->id . '/' . $longId,
            'long_id'        => $longId,
            'size'           => 1024 * $fileCount,
            'file_count'     => $fileCount,
            'download_limit' => null,
            'download_count' => 0,
            'require_email'  => false,
            'expires_at'     => now()->addDays(30),
            'status'         => 'ready',
        ], $attrs));

        for ($i = 0; $i < $fileCount; $i++) {
            File::create([
                'name'      => "file{$i}.txt",
                'size'      => 1024,
                'type'      => 'text/plain',
                'share_id'  => $share->id,
                'temp_path' => null,
                'full_path' => null,
            ]);
        }

        return $share;
    }

    private function makeUploadSession(User $user, string $filename = 'new.txt'): array
    {
        // Create a temp file to simulate an uploaded file
        $tmpDir = sys_get_temp_dir();
        $tmpFile = $tmpDir . '/' . Str::random(16);
        file_put_contents($tmpFile, str_repeat('x', 512));

        $uploadId = Str::random(16);
        $tempPath = 'uploads/' . $uploadId;

        // Copy temp file to where the controller expects it
        $destDir = storage_path('app/uploads');
        if (!is_dir($destDir)) mkdir($destDir, 0777, true);
        $destPath = storage_path('app/' . $tempPath);
        copy($tmpFile, $destPath);
        unlink($tmpFile);

        $file = File::create([
            'name'      => $filename,
            'size'      => 512,
            'type'      => 'text/plain',
            'share_id'  => null,
            'temp_path' => $tempPath,
            'full_path' => null,
        ]);

        $session = UploadSession::create([
            'upload_id'       => $uploadId,
            'user_id'         => $user->id,
            'filename'        => $filename,
            'filesize'        => 512,
            'filetype'        => 'text/plain',
            'total_chunks'    => 1,
            'chunks_received' => 1,
            'status'          => 'complete',
            'file_id'         => $file->id,
        ]);

        return ['uploadId' => $uploadId, 'file' => $file, 'session' => $session];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // addFilesToShare — auth & access
    // ──────────────────────────────────────────────────────────────────────────

    public function test_add_files_requires_auth(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, 2);

        $response = $this->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => ['x']]);
        $this->assertNotEquals(200, $response->status(), 'Unauthenticated request must not succeed');
    }

    public function test_add_files_non_owner_is_rejected(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $share = $this->makeShare($owner, 2);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => ['x']])
            ->assertStatus(401);
    }

    public function test_add_files_admin_can_manage_any_share(): void
    {
        $owner  = $this->makeUser();
        $admin  = $this->makeUser(admin: true);
        $share  = $this->makeShare($owner, 2);
        $upload = $this->makeUploadSession($admin, 'extra.txt');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", [
                'uploadIds' => [$upload['uploadId']],
                'filePaths' => [$upload['uploadId'] => 'extra.txt'],
            ])
            ->assertStatus(200);
    }

    public function test_add_files_to_deleted_share_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, 2, ['status' => 'deleted']);
        $upload = $this->makeUploadSession($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", [
                'uploadIds' => [$upload['uploadId']],
            ])
            ->assertStatus(422);
    }

    public function test_add_files_missing_upload_ids_returns_422(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, 2);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", [])
            ->assertStatus(422);
    }

    public function test_add_files_nonexistent_share_returns_404(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/shares/999999/add-files', ['uploadIds' => ['x']])
            ->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // addFilesToShare — success path
    // ──────────────────────────────────────────────────────────────────────────

    public function test_add_files_creates_file_record_and_sets_share_pending(): void
    {
        Queue::fake(); // Prevent CreateShareZip from running synchronously and overwriting 'pending'

        $owner  = $this->makeUser();
        $share  = $this->makeShare($owner, 2);
        $upload = $this->makeUploadSession($owner, 'third.txt');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", [
                'uploadIds' => [$upload['uploadId']],
                'filePaths' => [$upload['uploadId'] => 'third.txt'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $share->refresh();
        $this->assertEquals('pending', $share->status);
        $this->assertEquals(3, $share->file_count);
        $this->assertNotNull(File::where('share_id', $share->id)->where('name', 'third.txt')->first());
    }

    public function test_add_files_cleans_up_upload_session(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShare($owner, 2);
        $upload = $this->makeUploadSession($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", [
                'uploadIds' => [$upload['uploadId']],
                'filePaths' => [$upload['uploadId'] => 'new.txt'],
            ]);

        $this->assertNull(UploadSession::where('upload_id', $upload['uploadId'])->first());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // replaceShareFile — auth & access
    // ──────────────────────────────────────────────────────────────────────────

    public function test_replace_file_requires_auth(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, 1);

        $response = $this->postJson("/api/shares/{$share->id}/replace-file", ['uploadIds' => ['x']]);
        $this->assertNotEquals(200, $response->status(), 'Unauthenticated request must not succeed');
    }

    public function test_replace_file_non_owner_is_rejected(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $share = $this->makeShare($owner, 1);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/shares/{$share->id}/replace-file", ['uploadIds' => ['x']])
            ->assertStatus(401);
    }

    public function test_replace_file_on_multi_file_share_is_rejected(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShare($owner, 3);
        $upload = $this->makeUploadSession($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/replace-file", [
                'uploadIds' => [$upload['uploadId']],
            ])
            ->assertStatus(422);
    }

    public function test_replace_file_on_deleted_share_is_rejected(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShare($owner, 1, ['status' => 'deleted']);
        $upload = $this->makeUploadSession($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/replace-file", [
                'uploadIds' => [$upload['uploadId']],
            ])
            ->assertStatus(422);
    }

    public function test_replace_file_more_than_one_upload_id_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, 1);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/replace-file", [
                'uploadIds' => ['a', 'b'],
            ])
            ->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // replaceShareFile — success path
    // ──────────────────────────────────────────────────────────────────────────

    public function test_replace_file_swaps_file_record_and_stays_ready(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShare($owner, 1);
        $oldFileId = $share->files()->first()->id;
        $upload = $this->makeUploadSession($owner, 'replacement.txt');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/replace-file", [
                'uploadIds' => [$upload['uploadId']],
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $share->refresh();
        $this->assertEquals('ready', $share->status);
        $this->assertEquals(1, $share->file_count);
        $this->assertNull(File::find($oldFileId), 'Old file record must be deleted');
        $this->assertNotNull(File::where('share_id', $share->id)->where('name', 'replacement.txt')->first());
    }

    public function test_replace_file_updates_share_size(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShare($owner, 1);
        $upload = $this->makeUploadSession($owner, 'replacement.txt');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/replace-file", [
                'uploadIds' => [$upload['uploadId']],
            ]);

        $share->refresh();
        // The new file from makeUploadSession is 512 bytes
        $this->assertEquals(512, $share->size);
    }

    public function test_replace_file_cleans_up_upload_session(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShare($owner, 1);
        $upload = $this->makeUploadSession($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/replace-file", [
                'uploadIds' => [$upload['uploadId']],
            ]);

        $this->assertNull(UploadSession::where('upload_id', $upload['uploadId'])->first());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Share::cleanFiles() recursive delete regression
    // ──────────────────────────────────────────────────────────────────────────

    public function test_clean_files_recursively_removes_subdirectories(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShare($owner, 1);

        // Create a fake share directory with nested subdirs
        $sharePath = storage_path('app/shares/' . $share->path);
        @mkdir($sharePath . '/subdir/nested', 0777, true);
        file_put_contents($sharePath . '/subdir/nested/deep.txt', 'data');
        file_put_contents($sharePath . '/top.txt', 'data');

        $result = $share->cleanFiles(disableEmail: true);

        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($sharePath);
    }
}
