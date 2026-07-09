<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\RazorpayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyRazorpayPayment
{
    /**
     * Verify an incoming Razorpay callback and mark the payment as paid.
     *
     * FIX (Bug #3): The entire operation — including the SELECT — must live
     * inside DB::transaction() for lockForUpdate() to take effect.
     * Previously lockForUpdate() was called outside any transaction, making
     * the row-level lock a no-op and leaving a payment-duplication race window.
     */
    public function execute(array $payload): Payment
    {
        $verified = app(RazorpayService::class)
            ->verifySignature($payload);

        if (!$verified) {
            throw ValidationException::withMessages([
                'signature' => 'Invalid payment signature.',
            ]);
        }

        return DB::transaction(function () use ($payload) {

            $payment = Payment::query()
                ->where('order_id', $payload['razorpay_order_id'])
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment not found.',
                ]);
            }

            // The Razorpay webhook (server-to-server) and this browser-side
            // verify call race — either can arrive first. If the webhook
            // already moved this payment to 'processing', treat it the same
            // as 'paid': already being handled, return as-is. Without this,
            // falling through below would overwrite a paid_at the webhook
            // deliberately preserved, and cause the caller's own dedup check
            // to miss (since status would bounce back to 'paid'), dispatching
            // a second, redundant ProcessSuccessfulPayment job.
            if (in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_PROCESSING], true)) {
                return $payment;
            }

            $payment->update([
                'payment_id' => $payload['razorpay_payment_id'],
                'signature'  => $payload['razorpay_signature'],
                'status'     => Payment::STATUS_PAID,
                'paid_at'    => now(),
            ]);

            return $payment->fresh();
        });
    }
}
