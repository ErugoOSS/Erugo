<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;
use Illuminate\Support\Facades\Log;
use App\Jobs\RecalculatePhysicalStorage;

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
      RecalculatePhysicalStorage::dispatch();
      return;
    }

    try {
      $sourcePath = storage_path('app/shares/' . $user_folder . '/' . $this->share->long_id);
      $this->createZipFromDirectory($sourcePath, $zipPath);
      $this->share->status = 'ready';
      $this->share->save();
      RecalculatePhysicalStorage::dispatch();
      // Directory is intentionally kept alongside the zip so files can be
      // added later (F1) and cloned by reference (F3).
    } catch (\Exception $e) {
      // Don't leave a half-written zip behind: the check at the top would
      // treat it as finished on the next run.
      if (file_exists($zipPath)) {
        @unlink($zipPath);
      }
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

    $resolvedSource = realpath($sourcePath);
    if ($resolvedSource === false || !is_dir($resolvedSource)) {
      throw new \Exception('Source directory not found or not accessible');
    }
    $sourcePath = rtrim($resolvedSource, DIRECTORY_SEPARATOR);

    $zip = new \ZipArchive();
    $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    if ($result !== true) {
      throw new \Exception("Failed to create zip archive (ZipArchive code: {$result})");
    }

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
      // Skip symlinks so a link can't pull in a file from outside the share
      // folder. Uploads never create them, so anything here is unexpected.
      if ($file->isLink()) {
        Log::warning('Skipping symlink while zipping share', ['path' => $file->getPathname()]);
        continue;
      }

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
