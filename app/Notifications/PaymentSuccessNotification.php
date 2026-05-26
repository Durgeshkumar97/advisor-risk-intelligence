<?php

namespace App\Notifications;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(mixed $_notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $planName = optional($this->payment->plan)->name ?? 'RiskSignal';
        $amount = number_format((float) $this->payment->amount, 0);

        $loginToken = $notifiable->login_token;
        $dashboardUrl = $loginToken ? route('auto.login', ['token' => $loginToken]) : url('/dashboard');

        if (!empty($notifiable->phone)) {
            SendWhatsAppMessage::dispatch(
                type: 'welcome',
                phone: $notifiable->phone,
                userName: $notifiable->name,
                planName: $planName,
                dashboardUrl: $dashboardUrl,
            )->onQueue('whatsapp');
        }

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
