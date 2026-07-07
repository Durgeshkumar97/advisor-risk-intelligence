<?php

namespace App\Services\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExistingAccountLoginLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $loginUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('RiskSignal — your secure login link')
            ->greeting("Hi {$notifiable->name},")
            ->line('Someone just started a trial or checkout using this email address, and we found it already belongs to an existing RiskSignal account.')
            ->line('If that was you, use the secure link below to log in — no password needed.')
            ->action('Log In Securely', $this->loginUrl)
            ->line('This link expires in 15 minutes and can only be used once.')
            ->line('If you did not request this, no action is needed — nothing on your account has changed.')
            ->salutation('— Durgesh Kumar, Founder · RiskSignal');
    }
}
