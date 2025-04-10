<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Setting;
use App\Mail\shareDeletedWarningMail;
use App\Jobs\sendEmail;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Storage;

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
    'password'
  ];

  protected $casts = [
    'expires_at' => 'datetime',
    'deletes_at' => 'datetime',
  ];

  protected $appends = [
    'expired',
    'deletes_at',
    'deleted'
  ];

  protected $hidden = [
    'path',
    'user_id',
  ];

  //when creating a share, set the disk_id to the default disk
  protected static function boot()
  {
    parent::boot();
    static::creating(function ($share) {
      $defaultDisk = Disk::where('use_for_shares', true)->first();
      if ($defaultDisk) {
        $share->disk_id = $defaultDisk->id;
      } else {
        $share->disk_id = null;
      }
    });
  }

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

  public function disk()
  {
    return $this->belongsTo(Disk::class);
  }

  function getExpiredAttribute()
  {
    return $this->expires_at < now()->addMinutes(1);
  }

  function getDeletesAtAttribute()
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

    return $this->expires_at->addDays($cleanFilesAfterDays);
  }

  function getDeletedAttribute()
  {
    return $this->status == 'deleted';
  }

  function getShareDiskAttribute()
  {
    if ($this->disk_id) {
      return Storage::build([
        'driver' => $this->disk->driver,
        'key' => $this->disk->key,
        'secret' => $this->disk->secret,
        'region' => $this->disk->region,
        'bucket' => $this->disk->bucket,
        'url' => $this->disk->url,
        'endpoint' => $this->disk->endpoint,
        'use_path_style_endpoint' => $this->disk->use_path_style_endpoint,
        'root' => $this->disk->root,
      ]);
    }

    return Storage::disk('shares');
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


  public function cleanFiles($disableEmail = false)
  {
    try {
      $filePath = $this->path;


      $this->shareDisk->deleteDirectory($filePath);

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
