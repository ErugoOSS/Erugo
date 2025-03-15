<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;
use Illuminate\Support\Facades\Log;

class CreateShareZip implements ShouldQueue
{
  use Queueable;

  /**
   * Create a new job instance.
   */
  public function __construct(public Share $share)
  {
    //
  }

  /**
   * Execute the job.
   */
  public function handle(): void
  {

    //just check that we've not already created the zip file
    $zipPath = storage_path('app/shares/' . $this->share->user_id . '/' . $this->share->long_id . '.zip');
    if (file_exists($zipPath)) {
      return;
    }
    
    //if there is only one file just leave it alone and set the status to ready
    if ($this->share->file_count == 1) {
      $this->share->status = 'ready';
      $this->share->save();
      return;
    }
    
    try {
      $sourcePath = storage_path('app/shares/' . $this->share->user_id . '/' . $this->share->long_id);
      $this->createZipFromDirectory($sourcePath, $zipPath);
      $this->share->status = 'ready';
      $this->share->save();

      foreach ($this->share->files as $file) {
        unlink($sourcePath . '/' . $file->name);
      }
      rmdir($sourcePath);

    } catch (\Exception $e) {
      $this->share->status = 'failed';
      $this->share->save();
      Log::error('Error creating share zip: ' . $e->getMessage());
    }
  }

  function createZipFromDirectory($sourcePath, $zipPath) {

    // Ensure the zip directory exists
    $zipDir = dirname($zipPath);
    if (!is_dir($zipDir)) {
        mkdir($zipDir, 0755, true);
    }
    
    // Build the zip command to zip the entire directory
    $zipCommand = sprintf(
        'zip -r %s %s',
        escapeshellarg($zipPath),
        escapeshellarg('.')  // '.' represents current directory after we chdir
    );
        
    // Change to the source directory
    $currentDir = getcwd();
    chdir($sourcePath);
    
    // Execute the command
    $output = [];
    $returnCode = 0;
    exec($zipCommand . ' 2>&1', $output, $returnCode);
    
    // Change back to original directory
    chdir($currentDir);

    //did it work?
    if ($returnCode !== 0) {
      throw new \Exception('Failed to create zip file: ' . implode("\n", $output));
    }

    //check the zip file is valid
    if (!file_exists($zipPath)) {
      throw new \Exception('The zip operation completed but the zip file was not created');
    }

    //check the zip file is not empty
    if (filesize($zipPath) === 0) {
      throw new \Exception('The zip operation completed but the zip file was empty');
    }

    return true;
}
}
