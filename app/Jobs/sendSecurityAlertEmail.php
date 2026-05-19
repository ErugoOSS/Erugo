<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Jobs\sendEmail;
use App\Mail\malwareDetectedMail;
use App\Models\User;

class sendSecurityAlertEmail implements ShouldQueue
{
    use Queueable;

    public $filename;
    public $uploadId;
    public $threat;
    public $scanType;
    public $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filename, string $uploadId, string $threat, string $scanType, int $userId)
    {
        $this->filename = $filename;
        $this->uploadId = $uploadId;
        $this->threat = $threat;
        $this->scanType = $scanType;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $recipients = ['support@CompanyName.com'];

        $admins = User::where('admin', true)->get();
        foreach ($admins as $admin) {
            if (!in_array($admin->email, $recipients)) {
                $recipients[] = $admin->email;
            }
        }

        foreach ($recipients as $recipient) {
            sendEmail::dispatch(
                $recipient,
                malwareDetectedMail::class,
                [$this->filename, $this->uploadId, $this->threat, $this->scanType, $this->userId]
            );
        }
    }
}