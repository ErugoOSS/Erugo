<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Setting;
use App\Mail\shareDeletedWarningMail;
use App\Jobs\sendEmail;
use App\Services\SettingsService;
use App\Utils\PathGuard;
use Illuminate\Support\Facades\Log;

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

  /**
   * Statuses where the share link no longer works. A share waiting for
   * deletion is treated exactly like a deleted one.
   */
  public const GONE_STATUSES = ['deleted', 'pending_deletion'];

  public function isGone(): bool
  {
    return in_array($this->status, self::GONE_STATUSES, true);
  }

  /**
   * True once the expiry date has passed. A share with no expiry date
   * (expires_at is NULL, which admins can set) never expires.
   */
  public function isPastExpiry(): bool
  {
    return $this->expires_at !== null && $this->expires_at->isPast();
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


  /**
   * Remove a share folder and everything in it, subfolders included.
   *
   * This only removes directory entries. Cloned shares hard-link their files,
   * and unlink() on one link leaves the other names for that inode alone, so
   * the clone keeps its files. Symlinks are unlinked, never followed.
   */
  private function removeDirectoryRecursively(string $dir): bool
  {
    $items = scandir($dir);
    if ($items === false) {
      return false;
    }

    foreach ($items as $item) {
      if ($item === '.' || $item === '..') continue;
      $path = $dir . DIRECTORY_SEPARATOR . $item;
      if (is_link($path) || !is_dir($path)) {
        if (!unlink($path)) {
          return false;
        }
      } elseif (!$this->removeDirectoryRecursively($path)) {
        return false;
      }
    }

    return rmdir($dir);
  }

  /**
   * Path of a file inside this share's folder, relative to the folder, or
   * null if the stored path could climb out of it.
   */
  private function relativeFilePath(File $file): ?string
  {
    $relative = ($file->full_path ? trim($file->full_path, '/') . '/' : '') . $file->name;
    $parts = explode('/', $relative);
    if ($relative === '' || in_array('..', $parts, true) || in_array('', $parts, true)) {
      return null;
    }
    return $relative;
  }

  /**
   * Before 0.3.0, CreateShareZip removed a multi-file share's folder once the
   * zip was built, so older shares only have the zip. Adding files and
   * cloning both work from the loose files, so this unpacks any file the
   * folder is missing from the zip. Paths come from the files table, not
   * from the zip entries. Returns false when the zip exists but a missing
   * file could not be restored from it, since rebuilding the zip then would
   * drop that file.
   */
  public function restoreFolderFromZip(): bool
  {
    $folder = storage_path('app/shares/' . trim((string) $this->path, '/'));
    $missing = [];
    foreach ($this->files()->get() as $file) {
      $relative = $this->relativeFilePath($file);
      if ($relative === null) {
        return false;
      }
      if (!is_file($folder . '/' . $relative)) {
        $missing[] = $relative;
      }
    }

    if (count($missing) === 0) {
      return true;
    }

    // No zip means there is nothing to restore from. Rebuilding the zip can't
    // lose anything that isn't already gone, so let the caller carry on.
    $zipPath = $folder . '.zip';
    if (!is_file($zipPath)) {
      Log::warning('Share files missing on disk and no zip to restore from', ['share_id' => $this->id, 'missing' => count($missing)]);
      return true;
    }

    if (!is_dir($folder)) {
      mkdir($folder, 0750, true);
    }

    $zip = new \ZipArchive();
    if ($zip->open($zipPath) !== true) {
      return false;
    }

    $restored = 0;
    try {
      foreach ($missing as $relative) {
        $index = $zip->locateName($relative);
        if ($index === false) {
          continue;
        }
        $dest = $folder . '/' . $relative;
        if (!is_dir(dirname($dest))) {
          mkdir(dirname($dest), 0750, true);
        }
        if (!PathGuard::isWithin(dirname($dest), $folder) || file_exists($dest) || is_link($dest)) {
          continue;
        }
        $in = $zip->getStream($zip->getNameIndex($index));
        $out = $in ? fopen($dest, 'xb') : false;
        if ($in && $out) {
          stream_copy_to_stream($in, $out);
          $restored++;
        }
        if ($in) fclose($in);
        if ($out) fclose($out);
      }
    } finally {
      $zip->close();
    }

    Log::info('Restored share folder from its zip', ['share_id' => $this->id, 'files' => $restored]);

    return $restored === count($missing);
  }

  public function cleanFiles($disableEmail = false)
  {
    try {
      $sharesRoot = storage_path('app/shares');
      $filePath = trim((string) $this->path, '/');

      // An empty path would point at the shares root and wipe every share.
      if ($filePath === '') {
        Log::error('Refusing to clean share with an empty path', ['share_id' => $this->id]);
        return false;
      }

      $completePath = $sharesRoot . '/' . $filePath;

      if (is_link($completePath)) {
        unlink($completePath);
      } elseif (is_dir($completePath)) {
        $resolved = realpath($completePath);
        if (!PathGuard::isWithin($completePath, $sharesRoot) || $resolved === realpath($sharesRoot)) {
          Log::error('Refusing to clean share folder outside the shares root', ['share_id' => $this->id]);
          return false;
        }
        if (!$this->removeDirectoryRecursively($completePath)) {
          Log::error('Could not remove share folder', ['share_id' => $this->id]);
          return false;
        }
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

      if (!$disableEmail && $shouldSend && $this->user) {
        sendEmail::dispatch($this->user->email, shareDeletedWarningMail::class, ['share' => $this]);
      }

      return true;
    } catch (\Exception $e) {
      return false;
    }
  }
}
