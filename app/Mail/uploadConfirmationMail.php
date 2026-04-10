<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Share;
use App\Models\Setting;
use App\Models\User;

class uploadConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $share;
    public $user;
    public $recipients;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Share $share, $recipients)
    {
        $this->user = $user;
        $this->share = $share;
        $this->recipients = $recipients;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Setting::where('key', 'email_subject_uploadConfirmationMail.twig')->first()->value ?? 'Upload Confirmation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.uploadConfirmationMail',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
