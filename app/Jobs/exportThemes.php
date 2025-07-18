<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class exportThemes implements ShouldQueue
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

        Log::info('Exporting themes');

        //create a backup of the database
        $backupPath = base_path('database/themes.php');

        $themes = DB::table('themes')->get()->map(function($theme) {
            return (array) $theme;
        })->toArray();

        File::put($backupPath, "<?php\n\nreturn " . var_export($themes, true) . ";");


        Log::info('Themes exported: ' . count($themes));
    }
}
