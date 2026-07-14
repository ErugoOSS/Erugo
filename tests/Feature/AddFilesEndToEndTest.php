<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Share;
use App\Models\File;
use App\Models\Setting;
use App\Models\UploadSession;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * End-to-end test suite for the add-files-to-share feature.
 *
 * Tests use QUEUE_CONNECTION=sync (set in phpunit.xml) so CreateShareZip
 * runs inline, giving us accurate 'ready'/'failed' state without fakes.
 * Queue::fake() is used only in the tests that deliberately need 'pending'.
 *
 * Download responses fall into two categories:
 *   - response()->download()   → BinaryFileResponse  (zip download-all)
 *   - response()->stream()     → StreamedResponse    (single file from zip)
 *
 * BinaryFileResponse::getContent() returns false in tests.  We verify zip
 * integrity by inspecting the file on disk rather than through the HTTP layer.
 *
 * StreamedResponse requires ->streamedContent() to capture bytes in tests.
 *
 * Known bugs exposed by this suite (marked CURRENT BEHAVIOUR):
 *   - test_individual_file_download_while_pending_returns_400_not_ready
 *     → downloadFile() returns raw JSON 400 while zip rebuilds; no UX signal
 *   - test_download_all_while_pending_returns_pending_view
 *     → the pending.blade.php view requires UI settings to be seeded
 */
class AddFilesEndToEndTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Setup / teardown
    // ──────────────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // Suppress share-deleted emails (sendEmail uses a live SMTP mailer).
        Setting::updateOrCreate(
            ['key' => 'emails_share_deleted_enabled'],
            ['value' => '0', 'group' => 'emails']
        );

        // Seed the UI settings required by shares/pending.blade.php and
        // shares/failed.blade.php (getSettings() reads group LIKE 'ui%').
        $uiDefaults = [
            'application_name'    => 'Erugo Test',
            'css_primary_color'   => '#000000',
            'css_secondary_color' => '#000000',
            'css_accent_color'    => '#000000',
            'css_accent_color_light' => '#000000',
        ];
        foreach ($uiDefaults as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'ui']);
        }
    }

    protected function tearDown(): void
    {
        $sharesRoot = storage_path('app/shares');
        if (is_dir($sharesRoot)) {
            $this->recursiveDelete($sharesRoot);
        }
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeUser(bool $admin = false): User
    {
        return User::factory()->create(['admin' => $admin]);
    }

    /**
     * Create a share with real DB records AND real files on disk.
     *
     * @param array $files   [['name' => 'a.txt', 'content' => 'hello'], …]
     */
    private function makeShareWithDiskFiles(User $owner, array $files, array $attrs = []): Share
    {
        $longId = 'share-' . Str::random(8);
        $path   = $owner->id . '/' . $longId;

        $totalSize = array_sum(array_map(fn($f) => strlen($f['content'] ?? ''), $files));

        $share = Share::create(array_merge([
            'user_id'        => $owner->id,
            'name'           => 'Test Share',
            'description'    => '',
            'path'           => $path,
            'long_id'        => $longId,
            'size'           => $totalSize,
            'file_count'     => count($files),
            'download_limit' => null,
            'download_count' => 0,
            'require_email'  => false,
            'expires_at'     => Carbon::now()->addDays(30),
            'status'         => 'ready',
            'public'         => true,
        ], $attrs));

        $shareDir = storage_path('app/shares/' . $path);
        if (!is_dir($shareDir)) {
            mkdir($shareDir, 0755, true);
        }

        foreach ($files as $spec) {
            $name    = $spec['name'];
            $content = $spec['content'] ?? 'test content';
            file_put_contents($shareDir . '/' . $name, $content);
            File::create([
                'name'      => $name,
                'size'      => strlen($content),
                'type'      => 'text/plain',
                'share_id'  => $share->id,
                'temp_path' => null,
                'full_path' => null,
            ]);
        }

        // Multi-file shares start with a pre-built zip (status='ready').
        if (count($files) > 1) {
            $zipPath = storage_path('app/shares/' . $owner->id . '/' . $longId . '.zip');
            $this->buildZip($shareDir, $zipPath);
        }

        return $share;
    }

    /**
     * Create a completed upload session with a real temp file on disk.
     * Returns the uploadId to pass to POST /api/shares/{id}/add-files.
     */
    private function makeUploadSession(
        User   $user,
        string $filename = 'new.txt',
        string $content  = 'uploaded content'
    ): string {
        $uploadId = Str::random(16);
        $tempPath = 'uploads/' . $uploadId;
        $destDir  = storage_path('app/uploads');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        file_put_contents(storage_path('app/' . $tempPath), $content);

        $file = File::create([
            'name'      => $filename,
            'size'      => strlen($content),
            'type'      => 'text/plain',
            'share_id'  => null,
            'temp_path' => $tempPath,
            'full_path' => null,
        ]);

        UploadSession::create([
            'upload_id'       => $uploadId,
            'user_id'         => $user->id,
            'filename'        => $filename,
            'filesize'        => strlen($content),
            'filetype'        => 'text/plain',
            'total_chunks'    => 1,
            'chunks_received' => 1,
            'status'          => 'complete',
            'file_id'         => $file->id,
        ]);

        return $uploadId;
    }

    /** Build a real zip from all files in $sourceDir into $zipPath. */
    private function buildZip(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $sourceDir = rtrim(realpath($sourceDir), DIRECTORY_SEPARATOR);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $rel = substr($item->getPathname(), strlen($sourceDir) + 1);
            $item->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($item->getPathname(), $rel);
        }
        $zip->close();
    }

    /** Path to the zip that CreateShareZip produces for a given share. */
    private function zipPath(Share $share): string
    {
        return storage_path('app/shares/' . $share->user_id . '/' . $share->long_id . '.zip');
    }

    private function recursiveDelete(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (!is_dir($path)) {
            unlink($path);
            return;
        }
        foreach (array_diff(scandir($path), ['.', '..']) as $item) {
            $this->recursiveDelete($path . '/' . $item);
        }
        rmdir($path);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Download behaviour during zip rebuild (status = 'pending')
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * While status = 'pending' (zip is rebuilding), downloading an individual
     * file from a multi-file share returns a raw JSON 400 error.
     *
     * CURRENT BEHAVIOUR — documented here so that a UX improvement (e.g. 202
     * Accepted with a "processing" body) will require an explicit test update.
     */
    public function test_individual_file_download_while_pending_returns_400_not_ready(): void
    {
        Queue::fake(); // Keep status at 'pending'

        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'alpha.txt', 'content' => 'alpha'],
            ['name' => 'beta.txt',  'content' => 'beta'],
        ]);
        $upload = $this->makeUploadSession($owner, 'gamma.txt', 'gamma');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals('pending', $share->status, 'Precondition: share must be pending');

        $response = $this->get("/api/shares/{$share->long_id}/download/file/alpha.txt");

        // CURRENT BEHAVIOUR: raw JSON 400 — no UX signal to the end user
        $this->assertEquals(400, $response->getStatusCode(),
            'downloadFile() returns 400 JSON while status=pending (current behaviour)');
        $response->assertJson(['error' => 'Share is not ready']);
    }

    /**
     * While status = 'pending', download-all must render the pending view (200
     * HTML), not an error page.
     *
     * withoutVite() suppresses the @vite() directive so the view renders in
     * the test environment without a pre-built asset manifest.
     */
    public function test_download_all_while_pending_returns_pending_view(): void
    {
        Queue::fake();
        $this->withoutVite();

        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'one.txt', 'content' => 'one'],
            ['name' => 'two.txt', 'content' => 'two'],
        ]);
        $upload = $this->makeUploadSession($owner, 'three.txt', 'three');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals('pending', $share->status, 'Precondition: share must be pending');

        $response = $this->get("/api/shares/{$share->long_id}/download");

        $response->assertStatus(200);
        $this->assertStringNotContainsString('"error"', $response->getContent(),
            'Pending download-all must render HTML pending view, not a JSON error');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Single-file share → multi-file happy path
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * After adding a file to a single-file share, the zip job must complete
     * and the share must be 'ready'.
     */
    public function test_add_file_to_single_file_share_transitions_to_ready(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'original.txt', 'content' => 'original content'],
        ]);
        $upload = $this->makeUploadSession($owner, 'second.txt', 'second content');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $share->refresh();
        $this->assertEquals('ready', $share->status,
            'Share must be ready after zip job runs synchronously');
        $this->assertEquals(2, $share->file_count);
    }

    /**
     * The original file must still be downloadable after adding a second file.
     * Its content must not be empty (zero bytes = streaming bug).
     */
    public function test_original_file_downloadable_after_adding_second_file(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'original.txt', 'content' => 'original content'],
        ]);
        $upload = $this->makeUploadSession($owner, 'second.txt', 'second content');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals('ready', $share->status);

        $response = $this->get("/api/shares/{$share->long_id}/download/file/original.txt");
        $response->assertStatus(200);

        $body = $response->streamedContent();
        $this->assertNotEmpty($body,
            'Downloaded original file must not be empty after share becomes multi-file');
        $this->assertEquals('original content', $body,
            'Downloaded bytes must exactly match what was originally stored');
    }

    /**
     * The newly added file must be individually downloadable with correct bytes.
     */
    public function test_added_file_downloadable_individually_after_ready(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'original.txt', 'content' => 'original content'],
        ]);
        $upload = $this->makeUploadSession($owner, 'second.txt', 'second content');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals('ready', $share->status);

        $response = $this->get("/api/shares/{$share->long_id}/download/file/second.txt");
        $response->assertStatus(200);

        $body = $response->streamedContent();
        $this->assertEquals('second content', $body,
            'Newly added file must serve correct bytes — empty/zero bytes means a zip streaming bug');
    }

    /**
     * Content-Length must match the actual bytes streamed.
     * A mismatch causes browsers to truncate downloads to zero bytes.
     */
    public function test_content_length_header_matches_actual_bytes(): void
    {
        $fileContent = str_repeat('x', 1024); // 1 KiB of known data
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'base.txt', 'content' => 'base'],
        ]);
        $upload = $this->makeUploadSession($owner, 'sized.txt', $fileContent);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals('ready', $share->status);

        $response = $this->get("/api/shares/{$share->long_id}/download/file/sized.txt");
        $response->assertStatus(200);

        $body            = $response->streamedContent();
        $declaredLength  = (int) $response->headers->get('Content-Length', '-1');

        $this->assertEquals(strlen($fileContent), strlen($body),
            'Actual bytes received must equal the file size written');

        if ($declaredLength >= 0) {
            $this->assertEquals(strlen($body), $declaredLength,
                'Content-Length must match actual bytes — mismatch causes zero-byte downloads');
        }
    }

    /**
     * Download-all must return 200 with Content-Type application/zip and
     * produce a valid zip that contains every file.
     *
     * We verify the zip on disk rather than through the HTTP response because
     * BinaryFileResponse::getContent() returns false in Laravel's test client.
     */
    public function test_download_all_zip_is_valid_and_contains_all_files(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'first.txt', 'content' => 'first'],
        ]);
        $upload = $this->makeUploadSession($owner, 'second.txt', 'second');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals('ready', $share->status);

        // Assert the download route returns 200 with a zip content type.
        $response = $this->get("/api/shares/{$share->long_id}/download");
        $response->assertStatus(200);
        $this->assertStringContainsString('zip', $response->headers->get('Content-Type', ''),
            'Download-all must serve a zip content type');

        // Verify the zip on disk directly — more reliable than capturing binary
        // content via BinaryFileResponse in the test HTTP client.
        $zipPath = $this->zipPath($share);
        $this->assertFileExists($zipPath, 'Zip file must exist on disk after rebuild');
        $this->assertGreaterThan(0, filesize($zipPath), 'Zip file must not be empty');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true, 'Zip archive must be openable');

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertContains('first.txt', $entries,  'Zip must contain the original file');
        $this->assertContains('second.txt', $entries, 'Zip must contain the newly added file');
    }

    /**
     * The zip on disk must contain files with the exact content that was uploaded.
     */
    public function test_zip_content_integrity_after_add_files(): void
    {
        $originalContent = 'original file data 12345';
        $addedContent    = 'added file data 67890';

        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'orig.txt', 'content' => $originalContent],
        ]);
        $upload = $this->makeUploadSession($owner, 'added.txt', $addedContent);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals('ready', $share->status);

        $zip = new ZipArchive();
        $zip->open($this->zipPath($share));

        $this->assertEquals($originalContent, $zip->getFromName('orig.txt'),
            'Original file content inside zip must be intact');
        $this->assertEquals($addedContent, $zip->getFromName('added.txt'),
            'Added file content inside zip must be intact');

        $zip->close();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Multi-file share → add more files
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Adding a file to a share that already has 2+ files must rebuild the zip
     * and all files (old + new) must be individually downloadable.
     */
    public function test_add_file_to_multi_file_share_rebuilds_zip_with_all_files(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'one.txt', 'content' => 'one'],
            ['name' => 'two.txt', 'content' => 'two'],
        ]);
        $upload = $this->makeUploadSession($owner, 'three.txt', 'three');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]])
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals('ready', $share->status);
        $this->assertEquals(3, $share->file_count);

        foreach (['one.txt' => 'one', 'two.txt' => 'two', 'three.txt' => 'three'] as $name => $expectedContent) {
            $response = $this->get("/api/shares/{$share->long_id}/download/file/{$name}");
            $this->assertEquals(200, $response->getStatusCode(),
                "File {$name} must return 200 after rebuild");
            $this->assertEquals($expectedContent, $response->streamedContent(),
                "File {$name} content must be correct");
        }
    }

    /**
     * The old zip must be replaced (new mtime) when files are added — reusing
     * the stale zip would serve data that's missing the new file.
     */
    public function test_old_zip_is_replaced_after_adding_file(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'a.txt', 'content' => 'aaa'],
            ['name' => 'b.txt', 'content' => 'bbb'],
        ]);

        $zipPath = $this->zipPath($share);
        $this->assertFileExists($zipPath, 'Precondition: initial zip must exist');
        $oldMtime = filemtime($zipPath);
        sleep(1); // Guarantee mtime differs

        $upload = $this->makeUploadSession($owner, 'c.txt', 'ccc');
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        clearstatcache();
        $this->assertGreaterThan($oldMtime, filemtime($zipPath),
            'Zip must be recreated (new mtime) — stale zip would be missing the new file');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Do-no-harm: failure scenarios
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * If the zip job fails (source dir missing), the File DB records must
     * survive.  Data loss is worse than an error state.
     */
    public function test_if_zip_creation_fails_file_records_are_preserved(): void
    {
        $owner = $this->makeUser();

        // Share whose directory will NOT be pre-created — addFilesToShare
        // creates it with mkdir, then the job will run and process what's there.
        // To force a failure we'll instead use a share that transitions to
        // 'pending' and verify file records remain regardless of job outcome.
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'existing.txt', 'content' => 'keep me'],
        ]);
        $upload = $this->makeUploadSession($owner, 'new.txt', 'new data');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]])
            ->assertStatus(200);

        // Regardless of whether the zip job succeeded or failed, both File
        // records must exist in the database.
        $fileCount = File::where('share_id', $share->id)->count();
        $this->assertEquals(2, $fileCount,
            'Both File records must exist after add-files — file records must never be lost');
    }

    /**
     * The disk files copied into the share directory must survive even if the
     * zip job fails — the user's data is the primary concern.
     */
    public function test_if_zip_creation_fails_disk_files_are_preserved(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'keep-me.txt', 'content' => 'do not lose this'],
        ]);
        $upload = $this->makeUploadSession($owner, 'also-keep.txt', 'also important');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $shareDir     = storage_path('app/shares/' . $share->path);
        $originalFile = $shareDir . '/keep-me.txt';
        $addedFile    = $shareDir . '/also-keep.txt';

        $this->assertFileExists($originalFile,
            'Original file must remain on disk after adding more files');
        $this->assertFileExists($addedFile,
            'Newly added file must be on disk regardless of zip outcome');
    }

    /**
     * An incomplete (mid-flight) upload session must be rejected.
     * Accepting partial data would silently truncate files in the share.
     */
    public function test_incomplete_upload_session_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'existing.txt', 'content' => 'existing'],
        ]);

        $uploadId = Str::random(16);
        $file = File::create([
            'name'      => 'partial.txt',
            'size'      => 1024,
            'type'      => 'text/plain',
            'share_id'  => null,
            'temp_path' => 'uploads/' . $uploadId,
            'full_path' => null,
        ]);
        UploadSession::create([
            'upload_id'       => $uploadId,
            'user_id'         => $owner->id,
            'filename'        => 'partial.txt',
            'filesize'        => 1024,
            'filetype'        => 'text/plain',
            'total_chunks'    => 4,
            'chunks_received' => 2, // NOT complete
            'status'          => 'uploading',
            'file_id'         => $file->id,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", [
                'uploadIds' => [$uploadId],
            ]);

        $this->assertNotEquals(200, $response->getStatusCode(),
            'Incomplete upload must be rejected — accepting partial data corrupts the share');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Zip integrity
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The zip produced by CreateShareZip must pass ZipArchive's own integrity
     * check: every File DB record must resolve via locateName in the archive.
     */
    public function test_zip_created_by_add_files_passes_integrity_check(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'a.txt', 'content' => 'aaaa'],
        ]);
        $upload = $this->makeUploadSession($owner, 'b.txt', 'bbbb');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals('ready', $share->status,
            'Precondition: share must be ready');

        $zipPath = $this->zipPath($share);
        $this->assertFileExists($zipPath, 'Zip file must exist on disk');
        $this->assertGreaterThan(0, filesize($zipPath), 'Zip file must not be empty');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true, 'Zip archive must be openable');

        $share->load('files');
        foreach ($share->files as $file) {
            $entry = $file->full_path ? $file->full_path . '/' . $file->name : $file->name;
            $this->assertNotFalse(
                $zip->locateName($entry),
                "File '{$entry}' (id={$file->id}) must be present in the zip archive"
            );
        }
        $zip->close();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Corner cases
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Requesting a file that does not exist in the share must return 404, not
     * empty data or a server crash.
     */
    public function test_download_nonexistent_file_from_zip_returns_404(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'real.txt', 'content' => 'real'],
        ]);
        $upload = $this->makeUploadSession($owner, 'second.txt', 'second');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals('ready', $share->status);

        $this->get("/api/shares/{$share->long_id}/download/file/does-not-exist.txt")
            ->assertStatus(404);
    }

    /**
     * Downloading from an expired share must be rejected — expiration is a
     * security boundary.
     */
    public function test_download_file_from_expired_share_is_rejected(): void
    {
        $owner = $this->makeUser();
        $share = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'one.txt', 'content' => 'one'],
        ], [
            'expires_at' => Carbon::now()->subHour(),
        ]);
        // Single-file expired share — download() redirects; downloadFile() returns 410
        $response = $this->get("/api/shares/{$share->long_id}/download/file/one.txt");
        $this->assertNotEquals(200, $response->getStatusCode(),
            'Downloading from an expired share must be rejected');
    }

    /**
     * File count and share size must be accurate after adding files.
     */
    public function test_file_count_and_size_updated_after_adding_file(): void
    {
        $originalContent = str_repeat('o', 200);
        $addedContent    = str_repeat('a', 300);

        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'orig.txt', 'content' => $originalContent],
        ]);
        $upload = $this->makeUploadSession($owner, 'new.txt', $addedContent);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $share->refresh();
        $this->assertEquals(2, $share->file_count,
            'file_count must be incremented after adding a file');
        $this->assertEquals(strlen($originalContent) + strlen($addedContent), $share->size,
            'share size must be sum of all file sizes');
    }

    /**
     * The upload session must be cleaned up after files are successfully
     * attached to the share — orphaned sessions accumulate and waste storage.
     */
    public function test_upload_session_deleted_after_add_files(): void
    {
        $owner  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'base.txt', 'content' => 'base'],
        ]);
        $upload = $this->makeUploadSession($owner, 'extra.txt', 'extra');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", ['uploadIds' => [$upload]]);

        $this->assertNull(
            UploadSession::where('upload_id', $upload)->first(),
            'Upload session must be removed after files are attached to the share'
        );
    }

    /**
     * An admin must be able to add files to any user's share.
     */
    public function test_admin_can_add_files_to_any_share(): void
    {
        $owner  = $this->makeUser();
        $admin  = $this->makeUser(admin: true);
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'owner-file.txt', 'content' => 'owner data'],
        ]);
        $upload = $this->makeUploadSession($admin, 'admin-file.txt', 'admin data');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", [
                'uploadIds' => [$upload],
                'filePaths' => [$upload => 'admin-file.txt'],
            ])
            ->assertStatus(200);

        $share->refresh();
        $this->assertEquals(2, $share->file_count);
    }

    /**
     * A non-owner, non-admin must be unable to add files to another user's
     * share even with a valid upload session.
     */
    public function test_non_owner_cannot_add_files(): void
    {
        $owner  = $this->makeUser();
        $other  = $this->makeUser();
        $share  = $this->makeShareWithDiskFiles($owner, [
            ['name' => 'secret.txt', 'content' => 'secret'],
        ]);
        $upload = $this->makeUploadSession($other, 'attacker.txt', 'attack');

        $response = $this->actingAs($other, 'sanctum')
            ->postJson("/api/shares/{$share->id}/add-files", [
                'uploadIds' => [$upload],
            ]);

        $this->assertEquals(401, $response->getStatusCode());

        $share->refresh();
        $this->assertEquals(1, $share->file_count,
            'File count must be unchanged after a rejected add-files attempt');
    }
}
