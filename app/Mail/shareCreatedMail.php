<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Share;
use App\Models\Setting;
class shareCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $share;
    public $recipient;
    public $user;
    public $recipient_name;
    public $sender_name;

    public function __construct(User $user, Share $share, $recipient)
    {
        $this->user = $user;
        $this->share = $share;
        $this->recipient = $recipient;
        $this->recipient_name = explode(' ', $recipient['name'])[0];
        $this->sender_name = explode(' ', $user->name)[0];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Setting::where('key', 'email_subject_share_created')->first()->value,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.shareCreatedMail',
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
