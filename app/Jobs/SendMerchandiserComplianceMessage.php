<?php

namespace App\Jobs;

use App\Models\MerchandiserComplianceQuery;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMerchandiserComplianceMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $queryId)
    {
        $this->onConnection(config('performance.background_jobs.connection', 'deferred'));
        $this->onQueue('notifications');
    }

    public function handle(SmsService $sms): void
    {
        $query = MerchandiserComplianceQuery::with('user')->find($this->queryId);

        if (! $query || ! $query->user) {
            return;
        }

        $emailSent = (bool) $query->email_sent;
        $smsAttempted = in_array($query->channel, ['sms', 'email_sms'], true);
        $smsSent = (bool) $query->sms_sent;

        if (in_array($query->channel, ['email', 'email_sms'], true)) {
            try {
                $recipient = $query->user->contact_email ?: $query->user->email;

                if ($recipient) {
                    Mail::raw($query->message, function ($mail) use ($recipient, $query): void {
                        $mail->to($recipient)->subject($query->subject);
                    });

                    $emailSent = true;
                }
            } catch (\Throwable $exception) {
                Log::warning('Merchandiser compliance email failed: ' . $exception->getMessage(), [
                    'query_id' => $query->id,
                    'user_id' => $query->user_id,
                ]);
            }
        }

        if ($smsAttempted && $query->user->phone) {
            $smsSent = $sms->send($query->user->phone, $query->subject . "\n" . $query->message);
        }

        $query->update([
            'email_sent' => $emailSent,
            'sms_attempted' => $smsAttempted,
            'sms_sent' => $smsSent,
            'status' => 'sent',
        ]);
    }
}
