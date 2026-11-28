<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;
use App\Jobs\RecalculatePhysicalStorage;
use Illuminate\Support\Facades\Log;

class cleanExpiredShares implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Cleaning expired shares');
        $startTime = microtime(true);

        // Shares past the cleanup window (existing behaviour)
        $expiredShares = Share::readyForCleaning()->get();

        // Shares explicitly marked for deletion by a user or admin (F4/F5)
        $pendingShares = Share::where('status', 'pending_deletion')->get();

        $shares = $expiredShares->merge($pendingShares)->unique('id');

        Log::info('Found ' . $shares->count() . ' shares to clean (' .
            $expiredShares->count() . ' expired, ' . $pendingShares->count() . ' pending deletion)');

        foreach ($shares as $share) {
            Log::info('Cleaning share ' . $share->id);
            if ($share->cleanFiles()) {
                Log::info('Share ' . $share->id . ' cleaned');
            } else {
                Log::warning('Share ' . $share->id . ' could not be cleaned; will retry next run');
            }
        }

        if ($shares->count() > 0) {
            RecalculatePhysicalStorage::dispatch();
        }
        $endTime = microtime(true);
        $timeTaken = $endTime - $startTime;
        Log::info('Finished cleaning expired shares after ' . $timeTaken . ' seconds');
    }
}
