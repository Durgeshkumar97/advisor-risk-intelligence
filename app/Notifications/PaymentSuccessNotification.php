<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Successful')
            ->greeting('Welcome to RiskSignal')
            ->line('Your subscription has been activated.')
            ->line('Amount Paid: ₹' . $this->payment->amount)
            ->action('Go To Dashboard', url('/dashboard'))
            ->line('Thank you for subscribing.');
    }
}
