<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\User;
use App\Models\UploadSession;
use App\Models\ChunkUpload;
use App\Models\ReverseShareInvite;
use Illuminate\Support\Facades\Log;

class maintainDb implements ShouldQueue
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
        Log::info('Starting maintainDb job');

        //delete any chunks over 7 days old, these have probably been left by failed or aborted uploads
        Log::info('Deleting chunks over 7 days old: ' . ChunkUpload::where('created_at', '<', now()->subDays(7))->count() . ' chunks');
        ChunkUpload::where('created_at', '<', now()->subDays(7))->delete();

        //delete any upload sessions over 7 days old, these have probably been left by failed or aborted uploads
        Log::info('Deleting upload sessions over 7 days old: ' . UploadSession::where('created_at', '<', now()->subDays(7))->count() . ' sessions');
        UploadSession::where('created_at', '<', now()->subDays(7))->delete();

        //delete any guest users over 7 days old
        Log::info('Deleting guest users over 7 days old: ' . User::where('is_guest', true)->where('created_at', '<', now()->subDays(7))->count() . ' users');
        User::where('is_guest', true)->where('created_at', '<', now()->subDays(7))->delete();

        // Delete expired reverse share invites that were never used.
        // Only invites with used_at IS NULL are safe to remove — if used_at is set,
        // a share may have been created and the shares table holds a foreign key
        // (invite_id) pointing back to this record.
        $expiredUnused = ReverseShareInvite::where('expires_at', '<', now())
            ->whereNull('used_at')
            ->count();
        Log::info('Deleting expired unused reverse share invites: ' . $expiredUnused . ' invites');
        ReverseShareInvite::where('expires_at', '<', now())
            ->whereNull('used_at')
            ->delete();
    }
}
