<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;

class updateLegacySharePaths implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $shares = Share::where('path', 'like', '/var/www/html/storage/app/shares/%')->get();
        \Log::info('Found ' . $shares->count() . ' shares with legacy paths');
        foreach ($shares as $share) {
            \Log::info('Updating share path for share ' . $share->id);
            $share->path = str_replace('/var/www/html/storage/app/shares/', '', $share->path);
            $share->save();
        }
        \Log::info('Done updating share paths');
    }
}
