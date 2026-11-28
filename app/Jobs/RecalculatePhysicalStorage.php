<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Walk the shares directory and calculate actual disk usage by counting each
 * unique inode only once. Hard-linked files (e.g. cloned shares) share an inode
 * on disk, so this gives the true block-level consumption rather than the sum of
 * logical file sizes across all shares.
 *
 * Result is stored in the cache under 'physical_storage_bytes' and is triggered
 * by any event that changes share data on disk (upload complete, clone, delete,
 * replace file) rather than on a fixed schedule.
 */
class RecalculatePhysicalStorage implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $sharesRoot = storage_path('app/shares');

        if (!is_dir($sharesRoot)) {
            Cache::forever('physical_storage_bytes', 0);
            Cache::forever('physical_storage_calculated_at', now()->toIso8601String());
            return;
        }

        $seenInodes = [];
        $totalBytes = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sharesRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $stat = @stat($fileInfo->getPathname());
                if ($stat === false) {
                    continue;
                }

                // Key by device:inode — hard-linked files share an inode on the same device
                // and must only be counted once.
                $key = $stat['dev'] . ':' . $stat['ino'];
                if (!isset($seenInodes[$key])) {
                    $seenInodes[$key] = true;
                    $totalBytes += $stat['size'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('RecalculatePhysicalStorage: scan failed — ' . $e->getMessage());
            // Don't update cache on scan failure so the last good value is preserved.
            return;
        }

        Cache::forever('physical_storage_bytes', $totalBytes);
        Cache::forever('physical_storage_calculated_at', now()->toIso8601String());
    }
}
