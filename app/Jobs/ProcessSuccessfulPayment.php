<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Notifications\PaymentSuccessNotification;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessSuccessfulPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public Payment $payment
    ) {}

    public function handle(): void
    {
        $user = DB::transaction(function () {
            $payment = Payment::query()
                ->whereKey($this->payment->id)
                ->lockForUpdate()
                ->first();

            if (! $payment || $payment->processed_at) {
                return null;
            }

            return app(SubscriptionService::class)
                ->activate($payment);
        });

        if (! $user) {
            return;
        }

        $user->notify(
            new PaymentSuccessNotification($this->payment)
        );
    }
}
