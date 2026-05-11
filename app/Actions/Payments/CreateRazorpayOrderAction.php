<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Plan;
use App\Services\RazorpayService;
use Illuminate\Support\Str;

class CreateRazorpayOrderAction
{
    public function execute(Plan $plan, array $customerData): Payment
    {
        $receipt = 'RS-' . Str::uuid();

        $razorpay = app(RazorpayService::class);

        $order = $razorpay->createOrder([
            'receipt' => $receipt,
            'amount' => (int) ($plan->price * 100),
            'currency' => 'INR',
        ]);

        return Payment::create([
            'plan_id' => $plan->id,
            'name' => $customerData['name'],
            'email' => $customerData['email'],
            'phone' => $customerData['phone'],
            'gateway' => 'razorpay',
            'order_id' => $order['id'],
            'amount' => $plan->price,
            'currency' => 'INR',
            'status' => 'pending',
            'gateway_response' => $order,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
