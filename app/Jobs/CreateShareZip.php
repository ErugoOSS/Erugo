<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateShareZip implements ShouldQueue
{
  use Queueable;

  public $timeout = 30000;

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

    if ($this->share->user_id) {
      $user_folder = $this->share->user_id;
    } else {
      //grab the first segment of the path
      $user_folder = explode('/', $this->share->path)[0];
    }

    $zipPath = storage_path('app/shares_staging/' . $user_folder . '/' . $this->share->long_id . '.zip');

    try {
      $sourcePath = storage_path('app/shares_staging/' . $user_folder . '/' . $this->share->long_id);
      $this->createZipFromDirectory($sourcePath, $zipPath);
      $this->share->status = 'moving';
      $this->share->save();
      $this->removeDirectory($sourcePath);
      $this->moveZipToFinalLocation($zipPath);
      $this->share->status = 'ready';
      $this->share->save();
    } catch (\Exception $e) {
      $this->share->status = 'failed';
      $this->share->save();
      Log::error('Error creating share zip: ' . $e->getMessage());
    }
  }

  private function moveZipToFinalLocation($zipPath)
  {
    $this->share->shareDisk->putFileAs($this->share->path, $zipPath, $this->share->long_id . '.zip');
    unlink($zipPath);
  }

  function createZipFromDirectory($sourcePath, $zipPath)
  {

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

  private function removeDirectory($dir)
  {
    if (!file_exists($dir)) {
      return true;
    }

    if (!is_dir($dir)) {
      return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
      if ($item == '.' || $item == '..') {
        continue;
      }

      if (!$this->removeDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
        return false;
      }
    }

    return rmdir($dir);
  }
}
