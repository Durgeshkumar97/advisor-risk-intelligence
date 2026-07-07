<?php

namespace App\Services\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeSetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $setPasswordUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('RiskSignal — set your password')
            ->greeting("Welcome, {$notifiable->name}!")
            ->line('Your RiskSignal account is ready. Set a password so you can log back in anytime from any device.')
            ->action('Set My Password', $this->setPasswordUrl)
            ->line('This link expires in 24 hours. After that, use "Forgot password" on the login page.')
            ->line('If you face any issue, reply to this email — we respond within 2 hours.')
            ->salutation('— Durgesh Kumar, Founder · RiskSignal');
    }
}
