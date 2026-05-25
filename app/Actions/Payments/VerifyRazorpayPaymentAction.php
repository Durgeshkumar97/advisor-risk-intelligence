<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\RazorpayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyRazorpayPaymentAction
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
        return DB::transaction(function () use ($payload) {

            /*
            |----------------------------------------------------------------------
            | SELECT WITH ROW LOCK
            |----------------------------------------------------------------------
            |
            | lockForUpdate() only works inside an open transaction.
            | Any concurrent request for the same order_id will block here
            | until this transaction commits, preventing double-processing.
            |
            */

            $payment = Payment::query()
                ->where('order_id', $payload['razorpay_order_id'])
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment not found.',
                ]);
            }

            /*
            |----------------------------------------------------------------------
            | IDEMPOTENCY — already paid, return early
            |----------------------------------------------------------------------
            */

            if ($payment->status === 'paid') {
                return $payment;
            }

            /*
            |----------------------------------------------------------------------
            | SIGNATURE VERIFICATION
            |----------------------------------------------------------------------
            */

            $verified = app(RazorpayService::class)
                ->verifySignature($payload);

            if (!$verified) {
                throw ValidationException::withMessages([
                    'signature' => 'Invalid payment signature.',
                ]);
            }

            /*
            |----------------------------------------------------------------------
            | MARK PAID
            |----------------------------------------------------------------------
            */

            $payment->update([
                'payment_id' => $payload['razorpay_payment_id'],
                'signature'  => $payload['razorpay_signature'],
                'status'     => 'paid',
                'paid_at'    => now(),
            ]);

            return $payment->fresh();
        });
    }
}
