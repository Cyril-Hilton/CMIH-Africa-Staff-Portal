<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class MerchandiserResetPassword extends ResetPassword
{
    public function __construct(#[\SensitiveParameter] string $token, private readonly ?string $requestedEmail = null)
    {
        parent::__construct($token);
    }

    public function requestedEmail(): ?string
    {
        return $this->requestedEmail;
    }

    protected function resetUrl($notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $this->requestedEmail ?: $notifiable->getEmailForPasswordReset(),
            'portal' => 'merchandisers',
        ], false));
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Merchandiser Portal Password Reset')
            ->line('You are receiving this email because we received a password reset request for your merchandiser portal account.')
            ->action(Lang::get('Reset Password'), $url)
            ->line(Lang::get('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
            ->line('If you did not request a password reset, no further action is required.');
    }
}
