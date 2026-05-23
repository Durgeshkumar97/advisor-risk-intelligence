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

    public function __construct(
        public Payment $payment
    ) {}

    /*
    |--------------------------------------------------------------------------
    | CHANNELS
    |--------------------------------------------------------------------------
    |
    | WhatsApp is NOT a Laravel notification channel — it's a queued job
    | dispatched as a side-effect inside toMail(). This keeps delivery
    | decoupled: email always fires; WhatsApp fires only when the user
    | has a phone number on record.
    |
    */

    public function via(mixed $_notifiable): array
    {
        return ['mail'];
    }

    /*
    |--------------------------------------------------------------------------
    | MAIL  +  WHATSAPP WELCOME DISPATCH
    |--------------------------------------------------------------------------
    |
    | If the user was just created from this payment they will have a
    | login_token set. We embed it as a magic-link so they can access
    | their dashboard with one click — no password required.
    |
    | We also dispatch the WhatsApp welcome job here (if the user has a
    | phone number). Running inside a queue worker → the inner dispatch
    | goes onto the 'whatsapp' queue immediately.
    |
    */

    public function toMail(User $notifiable): MailMessage
    {
        $planName = optional($this->payment->plan)->name ?? 'RiskSignal';
        $amount   = number_format((float) $this->payment->amount, 0);

        /*
        |----------------------------------------------------------------------
        | MAGIC LINK — one-click dashboard access for new users
        |----------------------------------------------------------------------
        */

        $loginToken   = $notifiable->login_token;
        $dashboardUrl = $loginToken
            ? route('auto.login', ['token' => $loginToken])
            : url('/dashboard');

        /*
        |----------------------------------------------------------------------
        | WHATSAPP WELCOME (dispatched here, runs on 'whatsapp' queue)
        |----------------------------------------------------------------------
        |
        | Only fires when the user has a phone number. The job retries up
        | to 3 times with 30-second backoff if the API call fails.
        |
        */

        if (!empty($notifiable->phone)) {
            SendWhatsAppMessage::dispatch(
                type:         'welcome',
                phone:        $notifiable->phone,
                userName:     $notifiable->name,
                planName:     $planName,
                dashboardUrl: $dashboardUrl,
            )->onQueue('whatsapp');
        }

        /*
        |----------------------------------------------------------------------
        | MAIL MESSAGE
        |----------------------------------------------------------------------
        */

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
