<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait CheckCreateFolder
{
  private function checkCreateFolder($path, $disk = 'local')
  {
    if (!Storage::disk($disk)->exists($path)) {
      Storage::disk($disk)->makeDirectory($path);
    }
  }
}
