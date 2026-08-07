<?php

namespace App\Mail;

use App\Models\LeaveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveCoverNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LeaveApplication $leave,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Duty Cover Delegation Notification',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leave-cover',
            with: [
                'leave' => $this->leave,
                'staff' => $this->leave->user,
                'coveringStaff' => $this->leave->coveringStaff,
            ],
        );
    }
}
