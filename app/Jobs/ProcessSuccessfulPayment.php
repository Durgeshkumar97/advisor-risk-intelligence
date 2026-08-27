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
use Illuminate\Support\Facades\Log;

class ProcessSuccessfulPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

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

    /*
    |--------------------------------------------------------------------------
    | FAILED — retries exhausted, money is captured and fulfilment did not happen
    |--------------------------------------------------------------------------
    |
    | Without this handler the payment stayed at 'processing' forever. Every
    | downstream guard treats 'processing' as "already being handled" —
    | VerifyRazorpayPayment::execute() and WebhookController::handle() both
    | return early on it — so nothing would ever pick the payment back up. The
    | charge succeeded, no subscription existed, and the only trace was a row
    | in failed_jobs nobody reads.
    |
    | Moving it to 'requires_review' breaks that deadlock: the status is no
    | longer 'processing', so it is visible in the admin payments list and
    | cannot be mistaken for in-flight work. It is deliberately NOT 'paid' or
    | 'processing' (which would re-arm those same early returns) and NOT
    | 'failed' (no money moved in that state — see Payment::STATUS_FAILED).
    |
    | processed_at is left null: fulfilment genuinely did not complete, and
    | SubscriptionService::activate() uses it as its idempotency key, so a
    | manual replay must still be allowed to run.
    |
    */

    public function failed(?\Throwable $exception): void
    {
        Log::error('ProcessSuccessfulPayment: fulfilment failed after all retries — payment captured but not fulfilled.', [
            'payment_id' => $this->payment->id,
            'order_id' => $this->payment->order_id,
            'gateway_payment_id' => $this->payment->payment_id,
            'attempts' => $this->attempts(),
            'message' => $exception?->getMessage(),
        ]);

        try {
            DB::transaction(function () {
                $payment = Payment::query()
                    ->whereKey($this->payment->id)
                    ->lockForUpdate()
                    ->first();

                // A late-arriving retry or the webhook may have completed
                // fulfilment between the final failure and this handler.
                if (! $payment || $payment->processed_at) {
                    return;
                }

                $payment->update(['status' => Payment::STATUS_REQUIRES_REVIEW]);
            });
        } catch (\Throwable $e) {
            Log::critical('ProcessSuccessfulPayment: could not flag payment for review.', [
                'payment_id' => $this->payment->id,
                'message' => $e->getMessage(),
            ]);
        }

        // Primary alert. Sentry is wired up in bootstrap/app.php via
        // Integration::handles(), so report() surfaces this to the founder.
        if ($exception) {
            report($exception);
        }
    }
}
