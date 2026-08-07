<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SurveyBroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string  $recipientName,
        public string  $subject,
        public string  $body,
        public ?string $eventDate     = null,
        public ?string $eventTime     = null,
        public ?string $eventLocation = null,
        public ?string $eventMapUrl   = null,
        public ?string $surveyTitle   = null,
        public ?string $senderName    = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.survey-broadcast',
            with: [
                'recipientName' => $this->recipientName,
                'body'          => $this->body,
                'eventDate'     => $this->eventDate,
                'eventTime'     => $this->eventTime,
                'eventLocation' => $this->eventLocation,
                'eventMapUrl'   => $this->eventMapUrl,
                'surveyTitle'   => $this->surveyTitle,
                'senderName'    => $this->senderName,
            ],
        );
    }
}
