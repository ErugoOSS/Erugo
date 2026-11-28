<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Share;
use App\Jobs\sendEmail;
use App\Mail\shareExpiryWarningMail;
use App\Services\SettingsService;

class sendExpiryWarningEmails implements ShouldQueue
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

        $shouldSend = $settingsService->get('emails_share_expiry_warning_enabled') ?? true;

        if (!$shouldSend) {
            return;
        }

        $expiry_warning_days = $settingsService->get('expiry_warning_days') ?? 3;
        $shares = Share::where('expires_at', '<', now()->addDays($expiry_warning_days))
            ->where('expires_at', '>', now())
            ->where('sent_expiry_warning', false)
            ->whereNotIn('status', ['pending_deletion', 'deleted'])
            ->get();

        foreach ($shares as $share) {
            sendEmail::dispatch($share->user->email, shareExpiryWarningMail::class, ['share' => $share]);
            $share->sent_expiry_warning = true;
            $share->save();
        }
    }
}
