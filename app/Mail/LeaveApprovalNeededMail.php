<?php

namespace App\Mail;

use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveApprovalNeededMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LeaveApplication $leave,
        public User $approver,
        public bool $requestNotice = false,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->requestNotice ? 'Leave Request Notice — ' : 'Leave Approval Needed — ').$this->leave->user?->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leave-approval-needed',
            with: [
                'leave' => $this->leave,
                'staff' => $this->leave->user,
                'approver' => $this->approver,
                'lineManager' => $this->leave->lineManager,
                'coveringStaff' => $this->leave->coveringStaff,
                'isRequestNotice' => $this->requestNotice,
            ],
        );
    }
}
