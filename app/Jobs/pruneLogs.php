<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;


class pruneLogs implements ShouldQueue
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
    Log::info('Starting pruneLogs job');

    $logFiles = glob(storage_path('logs/*.log'));
    foreach ($logFiles as $logFile) {
      Log::info('Checking log file: ' . $logFile);
      //if the file exists and is older than 14 days, delete it
      if (file_exists($logFile) && filemtime($logFile) < time() - 14 * 24 * 60 * 60) {
        unlink($logFile);
      }
    }

    Log::info('Finished pruneLogs job');
  }
}
