<?php

namespace App\Mail;

use App\Models\LeaveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveApplicantStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LeaveApplication $leave,
        public string $statusLabel,
        public ?string $note = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Leave Request {$this->statusLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leave-applicant-status',
            with: [
                'leave' => $this->leave,
                'staff' => $this->leave->user,
                'lineManager' => $this->leave->lineManager,
                'coveringStaff' => $this->leave->coveringStaff,
                'statusLabel' => $this->statusLabel,
                'note' => $this->note,
            ],
        );
    }
}
