<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;
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
        $shares = Share::readyForCleaning()->get();
        Log::info('Found ' . $shares->count() . ' shares to clean');
        foreach ($shares as $share) {
            Log::info('Cleaning share ' . $share->id);
            $share->recipients()->delete();
            $share->cleanFiles();
            Log::info('Share ' . $share->id . ' cleaned');
        }
        $endTime = microtime(true);
        $timeTaken = $endTime - $startTime;
        Log::info('Finished cleaning expired shares after ' . $timeTaken . ' seconds');
    }
}
