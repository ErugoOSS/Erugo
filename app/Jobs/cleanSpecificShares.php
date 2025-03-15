<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;

class cleanSpecificShares implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $shareIds, public int $userId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->shareIds as $shareId) {
            $share = Share::find($shareId);
            if ($share && $share->user_id === $this->userId) {
                $share->cleanFiles(true);
            }
        }
    }
}
