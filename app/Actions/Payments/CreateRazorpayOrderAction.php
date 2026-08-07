<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Plan;
use Razorpay\Api\Api;

class CreateRazorpayOrderAction
{
    public function execute(
        Plan $plan,
        array $data
    ): Payment {

        /*
        |--------------------------------------------------------------------------
        | RAZORPAY API
        |--------------------------------------------------------------------------
        */

        $api = new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );

        /*
        |--------------------------------------------------------------------------
        | CREATE ORDER
        |--------------------------------------------------------------------------
        */

        $order = $api->order->create([

            'receipt' => 'rcpt_'.uniqid(),

            'amount' => (int) round(
                $plan->price * 100
            ),

            'currency' => 'INR',

            'payment_capture' => 1,
        ]);

        /*
        |--------------------------------------------------------------------------
        | STORE PAYMENT
        |--------------------------------------------------------------------------
        */

        return Payment::create([

            'plan_id' => $plan->id,

            'name' => $data['name'],

            'email' => $data['email'],

            'phone' => $data['phone'],

            'gateway' => 'razorpay',

            'order_id' => $order['id'],

            'amount' => $plan->price,

            'currency' => 'INR',

            'status' => 'pending',

            'gateway_response' => $order->toArray(),

            'ip_address' => request()->ip(),

            'user_agent' => request()->userAgent(),
        ]);
    }
}
