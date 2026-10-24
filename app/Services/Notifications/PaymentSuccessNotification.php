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
        $planName = $this->payment->plan->name;
        $amount = number_format((float) $this->payment->amount, 0);
        $dashboardUrl = route('dashboard');

        return (new MailMessage)
            ->subject('Welcome to RiskSignal — Your subscription is live')
            ->greeting("Welcome aboard, {$notifiable->name}!")
            ->line("Your **{$planName}** subscription has been activated.")
            ->line("**Amount Paid:** ₹{$amount}")
            ->line("Upload a client's holdings file and the risk report PDF is emailed to you once it is processed.")
            ->action('Open Your Dashboard', $dashboardUrl)
            ->line('Each report includes the portfolio risk score (0–100), the risk flags behind it, and every holding.')
            ->line('If you face any issue, reply to this email.')
            ->salutation('— Durgesh Kumar, Founder · RiskSignal');
    }
}
