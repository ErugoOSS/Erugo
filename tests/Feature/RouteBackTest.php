<?php

namespace Tests\Feature;

use App\Jobs\cleanExpiredShares;
use App\Models\File;
use App\Models\Share;
use App\Models\User;
use App\Services\PasswordTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Checks for merging RZA-SF's #276, #277, #278 and #282 into 0.3.0: pending
 * deletion on web and app downloads, clones surviving the original's
 * deletion, shares with no expiry, mixed-case login and token expiry.
 */
class RouteBackTest extends TestCase
{
    use RefreshDatabase;

    private array $createdDirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed();
        // Downloads and deletions queue emails; there is no mail server here.
        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdDirs as $dir) {
            $this->removeTree($dir);
            @unlink($dir . '.zip');
        }
        parent::tearDown();
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) && !is_link($path) ? $this->removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function makeUser(string $email, array $flags = []): User
    {
        return User::createAccount([
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
        ], $flags + ['active' => true]);
    }

    private function webToken(string $email): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk()->json('data.access_token');
    }

    /**
     * A share with real files on disk. $files maps relative path => content.
     */
    private function makeShare(User $owner, array $files, array $attrs = [], bool $zip = false): Share
    {
        $longId = 'share-' . Str::random(8);
        $path = $owner->id . '/' . $longId;
        $dir = storage_path('app/shares/' . $path);
        $this->createdDirs[] = $dir;
        mkdir($dir, 0755, true);

        $share = Share::create($attrs + [
            'user_id' => $owner->id,
            'name' => 'Route back share',
            'path' => $path,
            'long_id' => $longId,
            'size' => array_sum(array_map('strlen', $files)),
            'file_count' => count($files),
            'expires_at' => now()->addDays(7),
            'status' => 'ready',
        ]);

        foreach ($files as $relative => $content) {
            $full = $dir . '/' . $relative;
            if (!is_dir(dirname($full))) {
                mkdir(dirname($full), 0755, true);
            }
            file_put_contents($full, $content);
            $folder = trim(dirname($relative), '.');
            File::create([
                'name' => basename($relative),
                'original_name' => basename($relative),
                'size' => strlen($content),
                'type' => 'text/plain',
                'share_id' => $share->id,
                'full_path' => $folder !== '' ? $folder : null,
                'storage_id' => (string) Str::uuid(),
            ]);
        }

        if ($zip) {
            $archive = new \ZipArchive();
            $archive->open($dir . '.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            foreach ($files as $relative => $content) {
                $archive->addFromString($relative, $content);
            }
            $archive->close();
        }

        return $share;
    }

    private function sharePath(Share $share, string $relative = ''): string
    {
        return storage_path('app/shares/' . $share->path) . ($relative !== '' ? '/' . $relative : '');
    }

    // ── pending_deletion stops downloads, web and app ───────────────────────

    public function test_pending_deletion_share_is_not_served_on_web(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $single = $this->makeShare($owner, ['one.txt' => 'one'], ['status' => 'pending_deletion']);
        $multi = $this->makeShare($owner, ['a.txt' => 'a', 'b.txt' => 'b'], ['status' => 'pending_deletion'], zip: true);

        foreach ([$single, $multi] as $share) {
            $this->getJson("/api/shares/{$share->long_id}")->assertNotFound();

            // The full download sends the browser back to the share page
            // instead of handing over the file.
            $this->get("/api/shares/{$share->long_id}/download")
                ->assertRedirect('/shares/' . $share->long_id);
        }

        $this->get("/api/shares/{$single->long_id}/download/file/one.txt")->assertNotFound();
        $this->get("/api/shares/{$multi->long_id}/download/file/a.txt")->assertNotFound();
    }

    public function test_pending_deletion_share_is_not_served_on_app_api(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $single = $this->makeShare($owner, ['one.txt' => 'one'], ['status' => 'pending_deletion']);
        $multi = $this->makeShare($owner, ['a.txt' => 'a', 'b.txt' => 'b'], ['status' => 'pending_deletion'], zip: true);

        foreach ([$single, $multi] as $share) {
            $this->getJson("/api/app/v1/shares/{$share->long_id}")->assertNotFound();
            $this->getJson("/api/app/v1/shares/{$share->long_id}/download")->assertNotFound();
        }
        $this->getJson("/api/app/v1/shares/{$single->long_id}/files/one.txt")->assertNotFound();
        $this->getJson("/api/app/v1/shares/{$multi->long_id}/files/a.txt")->assertNotFound();

        $token = $this->webToken('owner@example.com');
        $this->withToken($token)
            ->postJson("/api/app/v1/shares/{$single->id}/extend", ['days' => 3])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SHARE_DELETED');

        // Listed with the deleted shares, not the active ones.
        $active = $this->withToken($token)->getJson('/api/app/v1/shares?status=active')->assertOk();
        $this->assertCount(0, $active->json('data.shares'));
        $deleted = $this->withToken($token)->getJson('/api/app/v1/shares?status=deleted')->assertOk();
        $this->assertCount(2, $deleted->json('data.shares'));
        $this->assertTrue($deleted->json('data.shares.0.pending_deletion'));
    }

    public function test_ready_single_file_share_still_downloads(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $share = $this->makeShare($owner, ['one.txt' => 'one']);

        $this->get("/api/shares/{$share->long_id}/download")->assertOk();
        $this->get("/api/app/v1/shares/{$share->long_id}/download")->assertOk();
    }

    // ── clones survive the original being deleted ───────────────────────────

    public function test_deleting_original_share_leaves_clone_files_on_disk(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $original = $this->makeShare($owner, [
            'top.txt' => 'top level',
            'sub/deep.txt' => 'in a subfolder',
        ]);

        $token = $this->webToken('owner@example.com');
        $cloneId = $this->withToken($token)
            ->postJson("/api/shares/{$original->id}/clone")
            ->assertOk()
            ->json('data.share.id');
        $clone = Share::find($cloneId);
        $this->createdDirs[] = $this->sharePath($clone);

        // The clone's files are hard links to the same inodes.
        $this->assertSame(
            fileinode($this->sharePath($original, 'sub/deep.txt')),
            fileinode($this->sharePath($clone, 'sub/deep.txt'))
        );

        // Ask for deletion and let the cleanup job remove the original.
        $this->withToken($token)->postJson("/api/shares/{$original->id}/request-deletion")->assertOk();
        (new cleanExpiredShares())->handle();

        $this->assertSame('deleted', $original->fresh()->status);
        $this->assertDirectoryDoesNotExist($this->sharePath($original));

        $this->assertSame('top level', file_get_contents($this->sharePath($clone, 'top.txt')));
        $this->assertSame('in a subfolder', file_get_contents($this->sharePath($clone, 'sub/deep.txt')));
        $this->assertNotContains($clone->fresh()->status, Share::GONE_STATUSES);
    }

    public function test_adding_a_same_named_file_does_not_change_the_clone(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $original = $this->makeShare($owner, ['a.txt' => 'original a', 'b.txt' => 'b']);
        $token = $this->webToken('owner@example.com');

        $cloneId = $this->withToken($token)->postJson("/api/shares/{$original->id}/clone")->assertOk()->json('data.share.id');
        $clone = Share::find($cloneId);
        $this->createdDirs[] = $this->sharePath($clone);

        // Upload a new a.txt into the original.
        $uploadId = Str::uuid()->toString();
        @mkdir(storage_path('app/uploads'), 0755, true);
        file_put_contents(storage_path('app/uploads/' . $uploadId), 'replacement a');
        $file = File::create([
            'name' => 'a.txt', 'original_name' => 'a.txt', 'size' => 13, 'type' => 'text/plain',
            'temp_path' => 'uploads/' . $uploadId, 'storage_id' => (string) Str::uuid(),
        ]);
        \App\Models\UploadSession::create([
            'upload_id' => $uploadId, 'user_id' => $owner->id, 'filename' => 'a.txt', 'filesize' => 13,
            'filetype' => 'text/plain', 'total_chunks' => 1, 'chunks_received' => 1,
            'status' => 'complete', 'file_id' => $file->id,
        ]);

        $this->withToken($token)->postJson("/api/shares/{$original->id}/add-files", [
            'uploadIds' => [$uploadId],
            'filePaths' => [$uploadId => 'a.txt'],
        ])->assertOk();

        $this->assertSame('replacement a', file_get_contents($this->sharePath($original, 'a.txt')));
        $this->assertSame('original a', file_get_contents($this->sharePath($clone, 'a.txt')));
        // The old row for a.txt is replaced, not left pointing at the new file.
        $this->assertSame(2, $original->files()->count());
    }

    public function test_clone_of_zip_only_share_restores_files(): void
    {
        $owner = $this->makeUser('owner@example.com');
        // A multi-file share as 0.2.16 left it: the zip, but no folder.
        $original = $this->makeShare($owner, ['a.txt' => 'aaa', 'sub/b.txt' => 'bbb'], zip: true);
        $this->removeTree($this->sharePath($original));

        $token = $this->webToken('owner@example.com');
        $cloneId = $this->withToken($token)->postJson("/api/shares/{$original->id}/clone")->assertOk()->json('data.share.id');
        $clone = Share::find($cloneId);
        $this->createdDirs[] = $this->sharePath($clone);

        $this->assertSame('aaa', file_get_contents($this->sharePath($clone, 'a.txt')));
        $this->assertSame('bbb', file_get_contents($this->sharePath($clone, 'sub/b.txt')));
    }

    public function test_clean_files_does_not_follow_symlinks(): void
    {
        $owner = $this->makeUser('owner@example.com');
        $outside = storage_path('app/routeback-outside-' . Str::random(6));
        mkdir($outside);
        file_put_contents($outside . '/keep.txt', 'keep');
        $this->createdDirs[] = $outside;

        $share = $this->makeShare($owner, ['a.txt' => 'a']);
        symlink($outside, $this->sharePath($share, 'link'));

        $this->assertTrue($share->cleanFiles(true));
        $this->assertFileExists($outside . '/keep.txt');
    }

    // ── shares with no expiry date ──────────────────────────────────────────

    public function test_share_with_no_expiry_lists_downloads_and_is_not_cleaned(): void
    {
        $owner = $this->makeUser('owner@example.com', ['admin' => true]);
        $share = $this->makeShare($owner, ['one.txt' => 'one'], ['expires_at' => null]);
        $token = $this->webToken('owner@example.com');

        $this->withToken($token)->getJson('/api/shares')->assertOk();
        $this->withToken($token)->getJson('/api/shares/all')->assertOk();
        $this->withToken($token)->getJson('/api/app/v1/shares?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data.shares');

        $this->getJson("/api/shares/{$share->long_id}")->assertOk();
        $this->get("/api/shares/{$share->long_id}/download")->assertOk();
        $this->getJson("/api/app/v1/shares/{$share->long_id}")->assertOk();

        // The app's extend sets now + days, which would shorten it.
        $this->withToken($token)->postJson("/api/app/v1/shares/{$share->id}/extend", ['days' => 7])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SHARE_NO_EXPIRY');
        $this->assertNull($share->fresh()->expires_at);

        (new cleanExpiredShares())->handle();
        $this->assertSame('ready', $share->fresh()->status);
        $this->assertFileExists($this->sharePath($share, 'one.txt'));

        $this->withToken($token)->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.shares.active', 1);
    }

    public function test_web_extend_rejects_bad_dates_and_huge_amounts(): void
    {
        $owner = $this->makeUser('admin@example.com', ['admin' => true]);
        $share = $this->makeShare($owner, ['one.txt' => 'one']);
        $token = $this->webToken('admin@example.com');

        $this->withToken($token)->postJson("/api/shares/{$share->id}/extend", ['expires_at' => 'not a date'])
            ->assertStatus(422);
        $this->withToken($token)->postJson("/api/shares/{$share->id}/extend", ['expires_at' => '9999-01-01'])
            ->assertStatus(422);

        // A huge amount is capped rather than overflowing the date.
        $this->withToken($token)->postJson("/api/shares/{$share->id}/extend", ['amount' => PHP_INT_MAX, 'unit' => 'days'])
            ->assertStatus(422);
        $this->assertTrue($share->fresh()->expires_at->lt(now()->addYears(11)));
    }

    // ── mixed-case login ────────────────────────────────────────────────────

    public function test_mixed_case_login_works_on_web_and_app(): void
    {
        $user = $this->makeUser('  Mixed.Case@Example.COM ');
        $this->assertSame('mixed.case@example.com', $user->fresh()->email);

        foreach (['MIXED.case@example.com', 'mixed.case@EXAMPLE.com', ' Mixed.Case@Example.COM'] as $typed) {
            $this->postJson('/api/auth/login', ['email' => $typed, 'password' => 'password'])
                ->assertOk();
            $this->postJson('/api/app/v1/auth/login', ['email' => $typed, 'password' => 'password'])
                ->assertOk()
                ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);
        }
    }

    public function test_app_admin_create_user_lowercases_email(): void
    {
        $this->makeUser('admin@example.com', ['admin' => true]);
        $token = $this->webToken('admin@example.com');

        $this->withToken($token)->postJson('/api/app/v1/users', [
            'email' => 'New.Person@Example.com',
            'name' => 'New Person',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'new.person@example.com']);

        // Same address in another case is a duplicate.
        $this->withToken($token)->postJson('/api/app/v1/users', [
            'email' => 'NEW.PERSON@example.com',
            'name' => 'Again',
        ])->assertStatus(422);
    }

    // ── token expiry ────────────────────────────────────────────────────────

    public function test_password_reset_tokens_still_expire_after_60_minutes(): void
    {
        $this->assertSame(60, config('auth.passwords.users.expire'));

        $user = $this->makeUser('reset@example.com');
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(400);

        $this->postJson('/api/app/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(400);
    }

    public function test_password_reset_token_works_within_60_minutes(): void
    {
        $user = $this->makeUser('reset@example.com');
        $token = Password::createToken($user);

        $this->travel(59)->minutes();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'Reset@Example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();
    }

    public function test_welcome_email_token_lasts_a_day(): void
    {
        $this->assertSame(1440, config('auth.passwords.account_setup.expire'));

        $user = $this->makeUser('welcome@example.com', ['must_change_password' => true]);
        $token = PasswordTokens::createSetupToken($user);

        $this->travel(23)->hours();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'welcome@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertFalse((bool) $user->fresh()->must_change_password);
        $this->assertSame(0, \DB::table('account_setup_tokens')->count());
    }

    public function test_welcome_email_token_expires_after_a_day(): void
    {
        $user = $this->makeUser('welcome@example.com');
        $token = PasswordTokens::createSetupToken($user);

        $this->travel(25)->hours();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'welcome@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(400);
    }

    public function test_admin_created_user_gets_a_setup_token_not_a_reset_token(): void
    {
        $this->makeUser('admin@example.com', ['admin' => true]);
        $token = $this->webToken('admin@example.com');

        $this->withToken($token)->postJson('/api/users', [
            'email' => 'Invited@Example.com',
            'name' => 'Invited',
        ])->assertOk();

        $this->assertSame(1, \DB::table('account_setup_tokens')->where('email', 'invited@example.com')->count());
        $this->assertSame(0, \DB::table('password_reset_tokens')->where('email', 'invited@example.com')->count());
    }
}
