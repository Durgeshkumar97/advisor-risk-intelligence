<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Razorpay\Api\Api;

class PaymentController extends Controller
{
    public function create(Request $request)
    {
        try {

            $validated = $request->validate([
                'name'  => 'required|string',
                'phone' => 'required',
                'email' => 'required|email',
                'plan'  => 'required|in:starter,pro,team',
            ]);

            $plans = [
                'starter' => 799,
                'pro'     => 2499,
                'team'    => 4999,
            ];

            $amount = $plans[$validated['plan']] * 100;

            $key = config('services.razorpay.key');
            $secret = config('services.razorpay.secret');

            if (!$key || !$secret) {
                return response()->json([
                    'success' => false,
                    'error' => 'Razorpay config missing'
                ], 500);
            }

            $api = new Api($key, $secret);

            $order = $api->order->create([
                'receipt'         => 'rcpt_' . time(),
                'amount'          => $amount,
                'currency'        => 'INR',
                'payment_capture' => 1,
            ]);

            return response()->json([
                'success'  => true,
                'order_id' => $order['id'],
                'amount'   => $amount,
                'key'      => $key,
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'phone'    => $validated['phone'],
            ]);

        } catch (\Exception $e) {

            \Log::error('RAZORPAY ERROR', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function verify(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Verification pending'
        ]);
    }

    public function success()
    {
        return view('payment-success');
    }
}