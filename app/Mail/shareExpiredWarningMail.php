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
use App\Mail\Concerns\RendersSubject;
class shareExpiredWarningMail extends Mailable
{
    use Queueable, SerializesModels, RendersSubject;

    /**
     * Create a new message instance.
     */
    public $share;

    public function __construct(Share $share)
    {
        $this->share = $share;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderSubject('email_subject_shareExpiredWarningMail.twig'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.shareExpiredWarningMail',
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
