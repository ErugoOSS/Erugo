<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Jobs\RecalculatePhysicalStorage;
use App\Jobs\CreateShareZip;
use App\Jobs\cleanSpecificShares;
use App\Models\User;
use App\Models\Share;
use App\Models\File;
use Tests\TestCase;
use Illuminate\Support\Str;

class PhysicalStorageTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeUser(bool $admin = false): User
    {
        return User::factory()->create(['admin' => $admin]);
    }

    private function makeShareWithFiles(User $owner, int $fileCount = 1): Share
    {
        $longId    = 'phys-test-' . Str::random(8);
        $sharePath = $owner->id . '/' . $longId;

        $share = Share::create([
            'user_id'        => $owner->id,
            'name'           => 'Physical Storage Test Share',
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
        ]);

        $shareDir = storage_path('app/shares/' . $sharePath);
        if (!is_dir($shareDir)) {
            mkdir($shareDir, 0755, true);
        }

        for ($i = 0; $i < $fileCount; $i++) {
            $filename  = "file{$i}.bin";
            $storageId = Str::uuid()->toString();

            file_put_contents($shareDir . '/' . $filename, str_repeat('x', 512));

            File::create([
                'name'          => $filename,
                'original_name' => $filename,
                'size'          => 512,
                'type'          => 'application/octet-stream',
                'share_id'      => $share->id,
                'temp_path'     => null,
                'full_path'     => null,
                'storage_id'    => $storageId,
            ]);
        }

        return $share;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Job: RecalculatePhysicalStorage
    // ──────────────────────────────────────────────────────────────────────────

    public function test_job_populates_physical_storage_cache(): void
    {
        Cache::forget('physical_storage_bytes');
        Cache::forget('physical_storage_calculated_at');

        $owner = $this->makeUser();
        $this->makeShareWithFiles($owner, 2);

        (new RecalculatePhysicalStorage())->handle();

        $this->assertTrue(Cache::has('physical_storage_bytes'));
        $this->assertTrue(Cache::has('physical_storage_calculated_at'));
        $this->assertIsInt(Cache::get('physical_storage_bytes'));
        $this->assertGreaterThan(0, Cache::get('physical_storage_bytes'));
    }

    public function test_job_counts_hard_linked_files_once(): void
    {
        Cache::forget('physical_storage_bytes');

        // Create an isolated test directory outside the shares root so other
        // test files on disk do not affect the inode count we're measuring.
        $testDir = storage_path('app/__phys_hardlink_test__');
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        $fileA = $testDir . '/fileA.bin';
        $fileB = $testDir . '/fileB.bin';  // distinct file
        $fileC = $testDir . '/fileC.bin';  // hard link of fileA — same inode

        try {
            file_put_contents($fileA, str_repeat('A', 1024));
            file_put_contents($fileB, str_repeat('B', 2048));
            link($fileA, $fileC);

            $statA = stat($fileA);
            $statC = stat($fileC);

            // Sanity-check that the hard link was created on the same device
            $this->assertEquals($statA['ino'], $statC['ino'], 'fileA and fileC must share an inode');

            // Baseline: physical before adding our test files
            (new RecalculatePhysicalStorage())->handle();
            $before = (int) Cache::get('physical_storage_bytes');

            // Move test dir inside shares root so the job picks it up
            $destDir = storage_path('app/shares/__phys_hardlink_test__');
            rename($testDir, $destDir);
            $fileA = str_replace($testDir, $destDir, $fileA);
            $fileB = str_replace($testDir, $destDir, $fileB);
            $fileC = str_replace($testDir, $destDir, $fileC);

            (new RecalculatePhysicalStorage())->handle();
            $after = (int) Cache::get('physical_storage_bytes');

            $added = $after - $before;

            // The two unique files add 1024 + 2048 = 3072 bytes.
            // fileC is a hard link of fileA — it must NOT be counted again.
            $this->assertEquals(3072, $added, 'Only unique inodes should be counted; hard link must not add to total');
        } finally {
            $dir = storage_path('app/shares/__phys_hardlink_test__');
            foreach (['fileA.bin', 'fileB.bin', 'fileC.bin'] as $f) {
                @unlink($dir . '/' . $f);
            }
            @rmdir($dir);
            @rmdir(storage_path('app/__phys_hardlink_test__'));
        }
    }

    public function test_job_returns_zero_when_shares_directory_missing(): void
    {
        Cache::forget('physical_storage_bytes');

        // Temporarily override storage path to a non-existent directory
        // by invoking the job and checking it handles missing dirs gracefully.
        // We can't easily change storage_path(), so we verify cache is set to 0
        // when no shares exist by clearing the test shares directory temporarily.
        $sharesRoot = storage_path('app/shares');
        $tmpPath    = storage_path('app/shares_bak_' . Str::random(6));

        if (is_dir($sharesRoot)) {
            rename($sharesRoot, $tmpPath);
        }

        try {
            (new RecalculatePhysicalStorage())->handle();
            $this->assertEquals(0, Cache::get('physical_storage_bytes'));
        } finally {
            if (is_dir($tmpPath)) {
                rename($tmpPath, $sharesRoot);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // StatsController: physical/logical metrics exposed
    // ──────────────────────────────────────────────────────────────────────────

    public function test_stats_endpoint_includes_physical_storage_keys(): void
    {
        $admin = $this->makeUser(admin: true);

        Cache::forever('physical_storage_bytes', 4096);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/stats')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $storage = $response->json('data.storage');

        $this->assertArrayHasKey('logical_bytes', $storage);
        $this->assertArrayHasKey('physical_bytes', $storage);
        $this->assertArrayHasKey('dedup_savings_bytes', $storage);
        $this->assertArrayHasKey('logical_formatted', $storage);
        $this->assertArrayHasKey('physical_formatted', $storage);

        $this->assertEquals(4096, $storage['physical_bytes']);

        Cache::forget('physical_storage_bytes');
    }

    public function test_stats_dedup_savings_is_logical_minus_physical(): void
    {
        $admin = $this->makeUser(admin: true);
        $owner = $this->makeUser();
        $this->makeShareWithFiles($owner, 4); // logical = 4 * 512 = 2048

        Cache::forever('physical_storage_bytes', 512); // pretend 3 are hard-linked

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/stats')
            ->assertStatus(200);

        $storage  = $response->json('data.storage');
        $logical  = $storage['logical_bytes'];
        $physical = $storage['physical_bytes'];
        $savings  = $storage['dedup_savings_bytes'];

        $this->assertGreaterThanOrEqual(0, $savings);
        $this->assertEquals(max(0, $logical - $physical), $savings);

        Cache::forget('physical_storage_bytes');
    }

    public function test_stats_physical_falls_back_to_logical_when_no_cache(): void
    {
        $admin = $this->makeUser(admin: true);

        Cache::forget('physical_storage_bytes');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/stats')
            ->assertStatus(200);

        $storage = $response->json('data.storage');

        // When no cache, physical falls back to logical — savings = 0
        $this->assertEquals(0, $storage['dedup_savings_bytes']);
        $this->assertEquals($storage['logical_bytes'], $storage['physical_bytes']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Dispatch: job is queued by share lifecycle events
    // ──────────────────────────────────────────────────────────────────────────

    public function test_create_share_zip_dispatches_recalculate_job_single_file(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 1);

        (new CreateShareZip($share))->handle();

        Queue::assertPushed(RecalculatePhysicalStorage::class);
    }

    public function test_create_share_zip_dispatches_recalculate_job_multi_file(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 2);

        (new CreateShareZip($share))->handle();

        Queue::assertPushed(RecalculatePhysicalStorage::class);
    }

    public function test_clean_specific_shares_dispatches_recalculate_job(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 1);

        cleanSpecificShares::dispatch([$share->id], $owner->id);

        Queue::assertPushed(cleanSpecificShares::class);
        // Run the job synchronously to verify it dispatches RecalculatePhysicalStorage
        Queue::assertPushed(cleanSpecificShares::class, function ($job) use ($share, $owner) {
            $job->handle();
            return true;
        });

        Queue::assertPushed(RecalculatePhysicalStorage::class);
    }

    public function test_clone_share_dispatches_recalculate_job(): void
    {
        Queue::fake();

        $owner = $this->makeUser();
        $share = $this->makeShareWithFiles($owner, 1);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/shares/{$share->id}/clone")
            ->assertStatus(200);

        Queue::assertPushed(RecalculatePhysicalStorage::class);
    }
}
