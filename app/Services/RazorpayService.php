<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class RazorpayService
{
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );
    }

    public function createOrder(array $data): array
    {
        return $this->api->order->create([
            'receipt' => $data['receipt'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'payment_capture' => 1,
        ])->toArray();
    }

    public function verifySignature(array $payload): bool
    {
        try {

            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $payload['razorpay_order_id'],
                'razorpay_payment_id' => $payload['razorpay_payment_id'],
                'razorpay_signature' => $payload['razorpay_signature'],
            ]);

            return true;

        } catch (\Throwable $e) {

            Log::error('Razorpay signature verification failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
