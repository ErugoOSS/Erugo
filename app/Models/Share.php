<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Setting;
use App\Mail\shareDeletedWarningMail;
use App\Jobs\sendEmail;
use App\Services\SettingsService;
class Share extends Model
{

  protected $fillable = [
    'user_id',
    'name',
    'description',
    'path',
    'long_id',
    'size',
    'file_count',
    'download_limit',
    'download_count',
    'require_email',
    'expires_at',
    'status',
    'password',
    'deletion_requested_at',
    'deletion_requested_by',
  ];

  protected $casts = [
    'expires_at' => 'datetime',
    'deletes_at' => 'datetime',
    'deletion_requested_at' => 'datetime',
  ];

  protected $appends = [
    'expired',
    'deletes_at',
    'deleted',
    'pending_deletion',
  ];

  protected $hidden = [
    'path',
    'user_id',
    'password',
  ];

  public function files()
  {
    return $this->hasMany(File::class);
  }

  public function getFileCountAttribute()
  {
    return $this->files()->count();
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function invite()
  {
    return $this->belongsTo(ReverseShareInvite::class, 'invite_id');
  }

  function getExpiredAttribute()
  {
    if ($this->expires_at === null) {
      return false; // null = no expiration (admin unlimited)
    }
    return $this->expires_at < now()->addMinutes(1);
  }

  function getDeletesAtAttribute()
  {
    if ($this->expires_at === null) {
      return null; // no expiration = no scheduled cleanup
    }

    $cleanFilesAfterDays = Setting::where('key', 'clean_files_after_days')->first();

    if (!$cleanFilesAfterDays) {
      $cleanFilesAfterDays = 30;
    } else {
      $cleanFilesAfterDays = (int) $cleanFilesAfterDays->value;
    }

    if ($cleanFilesAfterDays == 0) {
      $cleanFilesAfterDays = 30;
    }

    return $this->expires_at->addDays($cleanFilesAfterDays);
  }

  function getDeletedAttribute()
  {
    return $this->status == 'deleted';
  }

  function getPendingDeletionAttribute()
  {
    return $this->status === 'pending_deletion';
  }

  public function scopeReadyForCleaning($query)
  {
    $cleanFilesAfterDays = Setting::where('key', 'clean_files_after_days')->first();

    if (!$cleanFilesAfterDays) {
      $cleanFilesAfterDays = 30;
    } else {
      $cleanFilesAfterDays = (int) $cleanFilesAfterDays->value;
    }

    if ($cleanFilesAfterDays == 0) {
      $cleanFilesAfterDays = 30;
    }

    $deletesAt = now()->subDays($cleanFilesAfterDays);

    return $query->where('expires_at', '<', $deletesAt)->where('status', '!=', 'deleted');
  }


  private function removeDirectoryRecursively(string $dir): void
  {
    foreach (scandir($dir) as $item) {
      if ($item === '.' || $item === '..') continue;
      $path = $dir . DIRECTORY_SEPARATOR . $item;
      is_dir($path) ? $this->removeDirectoryRecursively($path) : unlink($path);
    }
    rmdir($dir);
  }

  public function cleanFiles($disableEmail = false)
  {
    try {
      $filePath = $this->path;
      $completePath = storage_path('app/shares/' . $filePath);

      if (is_dir($completePath)) {
        $this->removeDirectoryRecursively($completePath);
      }
      if (is_file($completePath . '.zip')) {
        unlink($completePath . '.zip');
      }

      // Clean thumbnail cache
      $thumbCachePath = storage_path('app/shares/thumbs/' . $this->id);
      if (is_dir($thumbCachePath)) {
        $thumbFiles = glob($thumbCachePath . '/*');
        foreach ($thumbFiles as $thumbFile) {
          if (is_file($thumbFile)) {
            unlink($thumbFile);
          }
        }
        rmdir($thumbCachePath);
      }

      $this->status = 'deleted';
      $this->save();

      if ($this->invite) {
        $this->invite->delete();
      }

      $settingsService = new SettingsService();

      $shouldSend = $settingsService->get('emails_share_deleted_enabled') ?? true;

      if (!$disableEmail && $shouldSend) {
        sendEmail::dispatch($this->user->email, shareDeletedWarningMail::class, ['share' => $this]);
      }

      return true;
    } catch (\Exception $e) {
      return false;
    }
  }
}
