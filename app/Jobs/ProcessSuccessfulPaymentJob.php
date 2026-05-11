<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Notifications\PaymentSuccessNotification;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSuccessfulPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Payment $payment
    ) {}

    public function handle(): void
    {
        if ($this->payment->processed_at) {
            return;
        }

        $user = app(SubscriptionService::class)
            ->activate($this->payment);

        $user->notify(
            new PaymentSuccessNotification($this->payment)
        );
    }
}
