<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Models\UploadSession;
use App\Models\ChunkUpload;
use Illuminate\Support\Facades\Log;

class maintainDb implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        
        Log::info('Starting maintainDb job');

        Log::info('Deleting chunks over 7 days old: ' . ChunkUpload::where('created_at', '<', now()->subDays(7))->count() . ' chunks');
        //delete any chunks over 7 days old, these have probably been left by failed or aborted uploads
        ChunkUpload::where('created_at', '<', now()->subDays(7))->delete();

        Log::info('Deleting upload sessions over 7 days old: ' . UploadSession::where('created_at', '<', now()->subDays(7))->count() . ' sessions');
        //delete any upload sessions over 7 days old, these have probably been left by failed or aborted uploads
        UploadSession::where('created_at', '<', now()->subDays(7))->delete();
    
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
