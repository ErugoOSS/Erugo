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


    if ($this->share->user_id) {
      $user_folder = $this->share->user_id;
    } else {
      //grab the first segment of the path
      $user_folder = explode('/', $this->share->path)[0];
    }

    //just check that we've not already created the zip file
    $zipPath = storage_path('app/shares/' . $user_folder . '/' . $this->share->long_id . '.zip');
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
      $sourcePath = storage_path('app/shares/' . $user_folder . '/' . $this->share->long_id);
      $this->createZipFromDirectory($sourcePath, $zipPath);
      $this->share->status = 'ready';
      $this->share->save();
      $this->removeDirectory($sourcePath);
    } catch (\Exception $e) {
      $this->share->status = 'failed';
      $this->share->save();
      Log::error('Error creating share zip: ' . $e->getMessage());
    }
  }

  function createZipFromDirectory($sourcePath, $zipPath)
  {
    // Ensure the zip directory exists
    $zipDir = dirname($zipPath);
    if (!is_dir($zipDir)) {
      mkdir($zipDir, 0755, true);
    }

    $zip = new \ZipArchive();
    $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    if ($result !== true) {
      throw new \Exception("Failed to create zip archive (ZipArchive code: {$result})");
    }

    $sourcePath = rtrim(realpath($sourcePath), DIRECTORY_SEPARATOR);
    if ($sourcePath === false || !is_dir($sourcePath)) {
      $zip->close();
      throw new \Exception('Source directory not found or not accessible');
    }

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
      $relativePath = substr($file->getPathname(), strlen($sourcePath) + 1);
      if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
      } else {
        $zip->addFile($file->getPathname(), $relativePath);
      }
    }

    if (!$zip->close()) {
      throw new \Exception('ZipArchive::close failed — archive may be incomplete');
    }

    if (!file_exists($zipPath)) {
      throw new \Exception('The zip operation completed but the zip file was not created');
    }

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
