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

    /**
     * Whether the three razorpay_* fields from the browser carry a signature
     * only Razorpay could have produced.
     *
     * The SDK computes the expected signature as an HMAC keyed on the secret
     * this Api object was built with (Utility::verifyPaymentSignature). With
     * RAZORPAY_SECRET unset that key is the empty string, and an HMAC keyed
     * on the empty string is something anyone can compute — so a missing env
     * var let any caller "verify" a payment for any order id they knew, and a
     * verified payment is fulfilled: marked paid, then given a subscription.
     *
     * So with no secret nothing verifies. It is the same false a bad
     * signature gets, which the caller turns into the same 422 before it
     * touches the database, so nothing outside can tell the secret is
     * missing. Inside it is report()ed rather than logged: every genuine
     * payment is being turned away while this is true.
     *
     * The value checked is the one the SDK will sign with (Api::getSecret()),
     * not a second read of config, so the guard cannot drift from the key
     * actually in use.
     */
    public function verifySignature(array $payload): bool
    {
        if (trim((string) Api::getSecret()) === '') {
            report(new \RuntimeException(
                'Razorpay payment verification refused: RAZORPAY_SECRET is not set, so no payment signature can be verified. '
                .'Checkout payments are not being fulfilled until it is configured.'
            ));

            return false;
        }

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
