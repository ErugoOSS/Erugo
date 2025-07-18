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

      if (Storage::directoryExists($filePath)) {
          Storage::deleteDirectory($filePath);
      }

      //or is it a zip file?
      if (Storage::fileExists($filePath . '.zip')) {
          Storage::delete($filePath . '.zip');
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
