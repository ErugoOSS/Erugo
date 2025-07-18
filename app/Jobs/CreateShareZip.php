<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use STS\ZipStream\Facades\Zip;
use ZipArchive;

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
    $zipPath = $user_folder . '/' . $this->share->long_id . '.zip';
    if (Storage::directoryExists($zipPath)) {
      return;
    }

    //if there is only one file just leave it alone and set the status to ready
    if ($this->share->file_count == 1) {
      $this->share->status = 'ready';
      $this->share->save();
      return;
    }

    try {
      $sourcePath = $user_folder . '/' . $this->share->long_id;
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
    $fileName = basename($zipPath);
    if (!Storage::directoryExists($zipDir)) {
        Storage::makeDirectory($zipDir);
    }

    $disk = config('filesystems.default');
    $zip = Zip::create($fileName);
    foreach (Storage::files($sourcePath, true) as $file) {
        $localPath = Str::replaceFirst($sourcePath . '/', '', $file);
        $zip->addFromDisk($disk, $file, $localPath);
    }
    $zip->saveToDisk($disk, $zipDir);

    return true;
  }

  private function removeDirectory($dir)
  {
    if (!Storage::exists($dir)) {
      return true;
    }

    if (Storage::fileExists($dir)) {
      return Storage::delete($dir);
    }

    Storage::deleteDirectory($dir);
  }
}
