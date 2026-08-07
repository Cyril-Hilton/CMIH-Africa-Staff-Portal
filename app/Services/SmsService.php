<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $phone, string $message): bool
    {
        $provider = config('services.sms.default', 'mnotify');

        if ($provider !== 'mnotify') {
            Log::warning('SMS provider not supported.', ['provider' => $provider]);
            return false;
        }

        $apiKey = config('services.mnotify.api_key');
        $sender = config('services.mnotify.sender_id', 'CMIH');
        $endpoint = config('services.mnotify.endpoint', 'https://api.mnotify.com/api/sms/quick');

        if (! $apiKey) {
            Log::warning('Missing Mnotify API key.');
            return false;
        }

        $payload = [
            'key' => $apiKey,
            'recipient' => [$phone],
            'sender' => $sender,
            'message' => $message,
        ];

        $response = Http::asForm()->post($endpoint, $payload);

        if (! $response->successful()) {
            Log::warning('SMS delivery failed.', ['status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->successful();
    }
}
