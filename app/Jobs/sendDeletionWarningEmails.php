<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;
use App\Jobs\sendEmail;
use App\Mail\shareDeletionWarningMail;
use App\Services\SettingsService;

class sendDeletionWarningEmails implements ShouldQueue
{
  use Queueable;

  /**
   * Create a new job instance.
   */
  public function __construct()
  {
    //
  }

  /**
   * Execute the job.
   */
  public function handle(): void
  {
    $settingsService = new SettingsService();

    $shouldSend = $settingsService->get('emails_share_deletion_warning_enabled') ?? true;

    if (!$shouldSend) {
      return;
    }

    $deletion_warning_days = $settingsService->get('deletion_warning_days') ?? 7;
    $clean_files_after_days = $settingsService->get('clean_files_after_days') ?? 30;

    $shares = Share::where('expires_at', '<=', now()->subDays($clean_files_after_days - $deletion_warning_days))
      ->where('sent_deletion_warning', false)
      ->whereNotIn('status', ['pending_deletion', 'deleted'])
      ->get();

    foreach ($shares as $share) {
      sendEmail::dispatch($share->user->email, shareDeletionWarningMail::class, ['share' => $share]);
      $share->sent_deletion_warning = true;
      $share->save();
    }
  }
}
