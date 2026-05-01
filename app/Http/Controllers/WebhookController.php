<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        $secret = env('RAZORPAY_WEBHOOK_SECRET');

        $expected = hash_hmac('sha256', $payload, $secret);

        if ($expected !== $signature) {
            Log::warning("Invalid webhook signature");
            return response()->json(['status' => 'invalid'], 400);
        }

        $event = $request->event;

        // Handle events
        switch ($event) {
            case 'payment.captured':
                Log::info("Payment captured", $request->all());
                break;

            case 'payment.failed':
                Log::warning("Payment failed", $request->all());
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}