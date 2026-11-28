<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;
use App\Models\ReverseShareInvite;
use App\Jobs\RecalculatePhysicalStorage;

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
            if (!$share) {
                continue;
            }
            // Direct owner
            if ($share->user_id === $this->userId) {
                $share->cleanFiles(true);
                continue;
            }

            // Reverse share: the user who sent the invite owns the invite, not
            // the share. Prune lists these shares too, so clean them as well.
            // Ported from RZA-SF's build/integrated branch.
            if ($share->invite_id &&
                ReverseShareInvite::where('id', $share->invite_id)
                    ->where('user_id', $this->userId)
                    ->exists()) {
                $share->cleanFiles(true);
            }
        }

        RecalculatePhysicalStorage::dispatch();
    }
}
