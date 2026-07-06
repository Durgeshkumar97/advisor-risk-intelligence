<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSuccessfulPayment;
use App\Models\Payment;
use App\Models\Subscription;
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

        if ($event === 'payment.refunded') {
            return $this->handleRefund($body);
        }

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

    /*
    |--------------------------------------------------------------------------
    | HANDLE REFUND  (event: payment.refunded)
    |--------------------------------------------------------------------------
    |
    | Fires on the same 'payment' entity as payment.captured (partial and full
    | refunds both send this event — revoke access either way, since this
    | system has no prorated/goodwill-refund use case that should leave
    | access intact).
    |
    | Revoking access requires touching BOTH the Payment AND the Subscription:
    | CheckSubscription (the permissive gate guarding /dashboard,
    | /portfolio/*, etc.) has a grace-period check keyed ONLY on ends_at —
    | it never reads status at all — so a status-only change would leave
    | access open until the original ends_at (plus 3 days) naturally passes.
    | Backdating ends_at is what actually closes that gate.
    |
    | Looked up via provider_subscription_id (populated with the Razorpay
    | payment_id by SubscriptionService::activate() /
    | CreateSubscriptionFromPaymentAction), NOT the Subscription::payment()
    | relation — that relation compares against Payment.order_id, but the
    | column it's compared to is actually populated with payment_id, so it
    | never resolves correctly. Not fixed here — out of scope for this pass.
    |
    */

    private function handleRefund(array $body): \Illuminate\Http\JsonResponse
    {
        $entity = $body['payload']['payment']['entity'] ?? null;

        if (!$entity || empty($entity['order_id'])) {
            Log::warning('Razorpay webhook: refund event missing payment entity or order_id');
            return response()->json(['error' => 'missing entity'], 400);
        }

        try {
            DB::transaction(function () use ($entity) {

                $payment = Payment::where('order_id', $entity['order_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$payment) {
                    Log::warning('Razorpay webhook: refund for unknown payment', [
                        'order_id' => $entity['order_id'],
                    ]);
                    return;
                }

                if ($payment->status === Payment::STATUS_REFUNDED) {
                    return;
                }

                $payment->update(['status' => Payment::STATUS_REFUNDED]);

                $subscription = Subscription::where('provider', 'razorpay')
                    ->where('provider_subscription_id', $payment->payment_id)
                    ->lockForUpdate()
                    ->first();

                if ($subscription) {
                    // Must land OUTSIDE CheckSubscription's 3-day grace window
                    // (now()->diffInDays($ends_at, false) >= -3), not just in
                    // the past — subDay() alone verified empirically to still
                    // satisfy that check (diff = -1), leaving access open for
                    // 3 more days. subDays(4) gives diff = -4, safely past it.
                    $subscription->update([
                        'status'   => 'cancelled',
                        'ends_at'  => now()->subDays(4),
                    ]);
                }

                Log::info('Razorpay webhook: payment refunded, access revoked', [
                    'payment_id'      => $payment->id,
                    'subscription_id' => $subscription?->id,
                ]);
            });

            return response()->json(['status' => 'ok']);

        } catch (\Throwable $e) {
            Log::error('Razorpay webhook: refund processing failed', [
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'order_id' => $entity['order_id'] ?? null,
            ]);

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
