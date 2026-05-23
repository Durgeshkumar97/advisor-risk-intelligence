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
use Illuminate\Support\Facades\Log;

class ProcessSuccessfulPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | QUEUE CONFIG
    |--------------------------------------------------------------------------
    */

    public int $tries   = 3;
    public int $backoff = 60;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        public Payment $payment
    ) {}

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    |
    | This job is the fallback path used when the webhook fires before the
    | browser-side verify call completes (or when verify itself fails).
    |
    | SubscriptionService::activate() owns all idempotency and duplicate
    | prevention internally — this job is safe to retry.
    |
    */

    public function handle(): void
    {
        /*
        |----------------------------------------------------------------------
        | REFRESH — always read the latest DB state, not the serialised snapshot
        |----------------------------------------------------------------------
        */

        $payment = $this->payment->fresh();

        if (!$payment) {

            Log::warning('ProcessSuccessfulPayment: payment record missing, aborting.', [
                'payment_id' => $this->payment->id,
            ]);

            return;
        }

        /*
        |----------------------------------------------------------------------
        | IDEMPOTENCY GUARD (checked on fresh model, not serialised snapshot)
        |----------------------------------------------------------------------
        */

        if ($payment->processed_at) {

            Log::info('ProcessSuccessfulPayment: already processed, skipping.', [
                'payment_id' => $payment->id,
            ]);

            return;
        }

        /*
        |----------------------------------------------------------------------
        | ACTIVATE
        |----------------------------------------------------------------------
        */

        try {

            app(SubscriptionService::class)->activate($payment);

        } catch (\Throwable $e) {

            Log::error('ProcessSuccessfulPayment: activation failed.', [
                'payment_id' => $payment->id,
                'attempt'    => $this->attempts(),
                'message'    => $e->getMessage(),
            ]);

            throw $e; // triggers retry
        }
    }
}
