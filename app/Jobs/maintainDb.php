<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\User;
use App\Models\UploadSession;
use App\Models\ChunkUpload;
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

        //delete any chunks over 1 days old, these have probably been left by failed or aborted uploads
        //only one day so as not to waste disk space
        $oldChunks = ChunkUpload::where('created_at', '<', now()->subDays(1))->get();
        Log::info('Deleting chunks over 1 days old: ' . $oldChunks->count() . ' chunks');
        $oldChunks->each->delete();

        //delete any upload sessions over 1 days old, these have probably been left by failed or aborted uploads
        //only one day so as not to waste disk space
        $oldSessions = UploadSession::where('created_at', '<', now()->subDays(1))->get();
        Log::info('Deleting upload sessions over 1 days old: ' . $oldSessions->count() . ' sessions');
        $oldSessions->each->delete();

        //delete any guest users over 7 days old
        $oldUsers = User::where('is_guest', true)->where('created_at', '<', now()->subDays(7))->get();
        Log::info('Deleting guest users over 7 days old: ' . $oldUsers->count() . ' users');
        $oldUsers->each->delete();
    }
}
