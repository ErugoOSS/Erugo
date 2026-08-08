<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\LiveshareInvite;
use App\Models\User;
use App\Models\Setting;

class liveshareInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invite;
    public $inviter;
    public $inviteUrl;
    public $liveshareName;
    public $inviterName;
    public $role;

    public function __construct(LiveshareInvite $invite, User $inviter, string $inviteUrl)
    {
        $this->invite = $invite;
        $this->inviter = $inviter;
        $this->inviteUrl = $inviteUrl;
        $this->liveshareName = $invite->liveshare->name;
        $this->inviterName = explode(' ', $inviter->name)[0];
        $this->role = $invite->role;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Setting::where('key', 'email_subject_liveshareInviteMail.twig')->first()->value ?? 'You have been invited to a liveshare',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.liveshareInviteMail',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
