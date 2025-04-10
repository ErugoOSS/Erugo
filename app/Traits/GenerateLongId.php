<?php

namespace App\Traits;

use App\Models\Share;
use App\Haikunator;

trait GenerateLongId
{
  public function generateLongId()
  {
    $maxAttempts = 10;
    $attempts = 0;
    $id = Haikunator::haikunate() . '-' . Haikunator::haikunate();
    while (Share::where('long_id', $id)->exists() && $attempts < $maxAttempts) {
      $id = Haikunator::haikunate() . '-' . Haikunator::haikunate();
      $attempts++;
    }
    if ($attempts >= $maxAttempts) {
      throw new \Exception('Unable to generate unique long_id after ' . $maxAttempts . ' attempts');
    }
    return $id;
  }
}
