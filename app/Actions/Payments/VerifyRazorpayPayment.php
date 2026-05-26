<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\RazorpayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyRazorpayPayment
{
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

            if ($payment->status === Payment::STATUS_PAID) {
                return $payment;
            }

            $payment->update([
                'payment_id' => $payload['razorpay_payment_id'],
                'signature' => $payload['razorpay_signature'],
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
            ]);

            return $payment->fresh();
        });
    }
}
