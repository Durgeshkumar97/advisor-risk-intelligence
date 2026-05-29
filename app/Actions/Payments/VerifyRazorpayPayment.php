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

            if ($payment->status === Payment::STATUS_PAID) {
                return $payment;
            }

            $verified = app(RazorpayService::class)
                ->verifySignature($payload);

            if (!$verified) {
                throw ValidationException::withMessages([
                    'signature' => 'Invalid payment signature.',
                ]);
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
