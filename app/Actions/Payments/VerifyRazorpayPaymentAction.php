<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\RazorpayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyRazorpayPaymentAction
{
    public function execute(array $payload): Payment
    {
        $payment = Payment::query()
            ->where('order_id', $payload['razorpay_order_id'])
            ->lockForUpdate()
            ->first();

        if (!$payment) {
            throw ValidationException::withMessages([
                'payment' => 'Payment not found.',
            ]);
        }

        if ($payment->status === 'paid') {
            return $payment;
        }

        $verified = app(RazorpayService::class)
            ->verifySignature($payload);

        if (!$verified) {
            throw ValidationException::withMessages([
                'signature' => 'Invalid payment signature.',
            ]);
        }

        DB::transaction(function () use ($payment, $payload) {

            $payment->update([
                'payment_id' => $payload['razorpay_payment_id'],
                'signature' => $payload['razorpay_signature'],
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        });

        return $payment->fresh();
    }
}
