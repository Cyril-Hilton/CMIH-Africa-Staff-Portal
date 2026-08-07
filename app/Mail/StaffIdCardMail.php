<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffIdCardMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your CMIH Staff ID Card',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-id-card',
            with: [
                'user' => $this->user,
                'downloadUrl' => route('portal.id-card'),
            ],
        );
    }
}
