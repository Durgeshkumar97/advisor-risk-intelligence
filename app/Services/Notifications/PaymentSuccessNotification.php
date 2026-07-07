<?php

namespace App\Services\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName     = $this->payment->plan->name;
        $amount       = number_format((float) $this->payment->amount, 0);
        $dashboardUrl = route('dashboard');

        return (new MailMessage)
            ->subject('Welcome to RiskSignal — Your subscription is live')
            ->greeting("Welcome aboard, {$notifiable->name}!")
            ->line("Your **{$planName}** subscription has been activated.")
            ->line("**Amount Paid:** ₹{$amount}")
            ->line('You will start receiving your daily risk signal by email each morning.')
            ->action('Open Your Dashboard', $dashboardUrl)
            ->line('Each report includes your portfolio risk score, top risk flags, and a ready-to-use client conversation script.')
            ->line('If you face any issue, reply to this email — we respond within 2 hours.')
            ->salutation('— Durgesh Kumar, Founder · RiskSignal');
    }
}
