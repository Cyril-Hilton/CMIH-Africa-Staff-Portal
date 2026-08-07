<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SmsService;
use Illuminate\Console\Command;

class SendIdentityVerificationSms extends Command
{
    protected $signature = 'staff:send-identity-verification-sms
        {--send : Send the SMS instead of showing recipient coverage only}';

    protected $description = 'Notify active staff that portal identity verification is available but optional';

    public function handle(SmsService $sms): int
    {
        $staff = User::internalStaff()
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'name', 'phone']);

        $recipients = $staff
            ->filter(fn (User $user) => filled($user->phone))
            ->mapWithKeys(function (User $user) {
                $phone = $this->normalisePhone((string) $user->phone);

                return $phone === '' ? [] : [$phone => $user];
            });

        $missingPhoneCount = $staff->count() - $staff->filter(fn (User $user) => filled($user->phone))->count();

        $this->table(
            ['Active staff', 'Unique SMS recipients', 'Missing phone'],
            [[$staff->count(), $recipients->count(), $missingPhoneCount]]
        );

        if (! $this->option('send')) {
            $this->info('Dry run only. Re-run with --send to deliver the notification.');

            return self::SUCCESS;
        }

        $message = 'CMIH Portal update: Identity Verification in Profile is now optional for all staff and does not restrict portal access. You may upload a national ID or passport later.';
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $phone => $user) {
            try {
                $sms->send($phone, $message) ? $sent++ : $failed++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->table(['Sent', 'Failed'], [[$sent, $failed]]);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function normalisePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';

        if (str_starts_with($phone, '00')) {
            return '+'.substr($phone, 2);
        }

        return $phone;
    }
}
