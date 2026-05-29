<?php

namespace App\Notifications;

use App\Jobs\SendWhatsAppMessage;
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
        return (new MailMessage)
            ->subject('Welcome to RiskSignal — Your subscription is live')
            ->greeting("Welcome aboard, {$notifiable->name}!")
            ->line("Your **{$planName}** subscription has been activated.")
            ->line("**Amount Paid:** ₹{$amount}")
            ->line('You will start receiving daily risk signals at **4:30 PM on WhatsApp** and at 8:00 AM by email.')
            ->action('Open Your Dashboard', $dashboardUrl)
            ->line('Each report includes your portfolio risk score, top risk flags, and a ready-to-use client conversation script.')
            ->line('If you face any issue, reply to this email — we respond within 2 hours.')
            ->salutation('— Durgesh Kumar, Founder · RiskSignal');
    }
}
