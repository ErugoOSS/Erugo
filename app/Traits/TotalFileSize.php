<?php

namespace App\Traits;

trait TotalFileSize
{
  private function getTotalFileSize($files)
  {
    $totalFileSize = 0;
    foreach ($files as $file) {
      $totalFileSize += $file->getSize();
    }
    return $totalFileSize;
  }
}
