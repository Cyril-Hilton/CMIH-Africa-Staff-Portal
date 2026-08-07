<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your CMIH Portal Access Details',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal-credentials',
            with: [
                'user' => $this->user,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => route('login'),
            ],
        );
    }
}
