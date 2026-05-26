<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSuccessfulPayment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | STEP 1 — Capture raw payload BEFORE any decoding
        | getContent() must be called on the raw body for HMAC to match.
        |--------------------------------------------------------------------------
        */
        $payload   = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        /*
        |--------------------------------------------------------------------------
        | STEP 2 — Verify HMAC signature
        | Use config() not env() — env() returns null after config is cached
        | in production (php artisan config:cache).
        | hash_equals() prevents timing attacks instead of !==
        |--------------------------------------------------------------------------
        */
        $expected = hash_hmac(
            'sha256',
            $payload,
            (string) config('services.razorpay.webhook_secret')
        );

        if (! hash_equals($expected, (string) $signature)) {
            Log::warning('Razorpay webhook: invalid signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'invalid signature'], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 3 — Decode JSON body
        | $request->event / $request->payload do NOT work on raw webhook bodies.
        | Always decode getContent() manually.
        |--------------------------------------------------------------------------
        */
        $body = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Razorpay webhook: invalid JSON body');

            return response()->json(['error' => 'invalid payload'], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 4 — Ignore non-captured events (return 200 so Razorpay stops retrying)
        |--------------------------------------------------------------------------
        */
        $event = $body['event'] ?? '';

        if ($event !== 'payment.captured') {
            return response()->json(['status' => 'ignored']);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 5 — Extract payment entity safely
        |--------------------------------------------------------------------------
        */
        $entity = $body['payload']['payment']['entity'] ?? null;

        if (! $entity || empty($entity['order_id'])) {
            Log::warning('Razorpay webhook: missing payment entity or order_id', [
                'body' => $body,
            ]);

            return response()->json(['error' => 'missing entity'], 400);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP 6 — Process inside a DB transaction with row-level lock
        |--------------------------------------------------------------------------
        */
        try {

            DB::transaction(function () use ($entity) {

                /*
                |----------------------------------------------------------------------
                | Lock payment row to prevent duplicate processing
                |----------------------------------------------------------------------
                */
                $payment = Payment::where('order_id', $entity['order_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    Log::warning('Razorpay webhook: payment record not found', [
                        'order_id' => $entity['order_id'],
                    ]);
                    return;
                }

                /*
                |----------------------------------------------------------------------
                | Idempotency guard — already processed
                |----------------------------------------------------------------------
                */
                if (
                    $payment->processed_at !== null ||
                    $payment->status === Payment::STATUS_PROCESSING
                ) {
                    return;
                }

                /*
                |----------------------------------------------------------------------
                | Mark payment as ready for fulfilment.
                |----------------------------------------------------------------------
                */
                $payment->update([
                    'payment_id' => $entity['id'],
                    'status'     => Payment::STATUS_PROCESSING,
                    'paid_at'    => $payment->paid_at ?? now(),
                ]);

                DB::afterCommit(fn() => ProcessSuccessfulPayment::dispatch(
                    $payment->fresh()
                ));
            });

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            /*
             | Catch \Throwable (not just \Exception) to also catch errors like
             | TypeError, which would otherwise silently swallow the failure.
             | Return 500 so Razorpay retries the webhook.
             */
            Log::error('Razorpay webhook: processing failed', [
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'order_id' => $entity['order_id'] ?? null,
            ]);

            return response()->json(['error' => 'server error'], 500);
        }
    }
}
