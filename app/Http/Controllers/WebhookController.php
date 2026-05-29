<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSuccessfulPayment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | HANDLE RAZORPAY WEBHOOK
    |--------------------------------------------------------------------------
    |
    | Razorpay fires this as a server-to-server backup when the browser
    | closes before the JS verify call completes.
    |
    | Safety guarantees:
    |   1. HMAC signature verified before any processing.
    |   2. Only payment.captured events acted on.
    |   3. payment->processed_at used as idempotency lock (lockForUpdate).
    |   4. Fulfilment delegated to ProcessSuccessfulPayment job.
    |
    */

    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        $expected = hash_hmac(
            'sha256',
            $payload,
            (string) config('services.razorpay.webhook_secret')
        );

        if (!hash_equals($expected, (string) $signature)) {
            Log::warning('Razorpay webhook: invalid signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $body = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Razorpay webhook: invalid JSON body');
            return response()->json(['error' => 'invalid payload'], 400);
        }

        $event = $body['event'] ?? '';

        if ($event !== 'payment.captured') {
            return response()->json(['status' => 'ignored']);
        }

        $entity = $body['payload']['payment']['entity'] ?? null;

        if (!$entity || empty($entity['order_id'])) {
            Log::warning('Razorpay webhook: missing payment entity or order_id', ['body' => $body]);
            return response()->json(['error' => 'missing entity'], 400);
        }

        try {
            DB::transaction(function () use ($entity) {

                $payment = Payment::where('order_id', $entity['order_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$payment) {
                    Log::warning('Razorpay webhook: payment record not found', [
                        'order_id' => $entity['order_id'],
                    ]);
                    return;
                }

                if ($payment->processed_at !== null || $payment->status === Payment::STATUS_PROCESSING) {
                    return;
                }

                $payment->update([
                    'payment_id' => $entity['id'],
                    'status'     => Payment::STATUS_PROCESSING,
                    'paid_at'    => $payment->paid_at ?? now(),
                ]);

                DB::afterCommit(fn() => ProcessSuccessfulPayment::dispatch($payment->fresh()));
            });

            return response()->json(['status' => 'ok']);

        } catch (\Throwable $e) {
            Log::error('Razorpay webhook: processing failed', [
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'order_id' => $entity['order_id'] ?? null,
            ]);

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
