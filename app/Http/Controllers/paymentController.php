<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Subscription;

class PaymentController extends Controller
{
    protected $api;

    public function __construct()
    {
        $this->api = new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );
    }

    //CREATE ORDER
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'plan' => 'required'
        ]);

        // Plan pricing logic
        $plans = [
            'starter' => 99900,
            'pro' => 249900,
            'team' => 499900,
        ];

        if (!isset($plans[$validated['plan']])) {
            return response()->json(['success' => false, 'error' => 'Invalid plan']);
        }

        $amount = $plans[$validated['plan']];

        try {
            $order = $this->api->order->create([
                'receipt' => uniqid(),
                'amount' => $amount,
                'currency' => 'INR',
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order['id'],
                'amount' => $amount,
                'key' => config('services.razorpay.key')
            ]);

        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Order creation failed'
            ]);
        }
    }

    //VERIFY PAYMENT
    public function verify(Request $request)
    {
        try {

            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ];

            $this->api->utility->verifyPaymentSignature($attributes);

            //Fraud Check (basic)
            if (!$request->email || !$request->phone) {
                throw new \Exception("Fraud suspected");
            }

            // 👤 Create / fetch user
            $user = User::firstOrCreate(
                ['email' => $request->email],
                [
                    'name' => $request->name,
                    'password' => bcrypt('password') // temp
                ]
            );

            //Save subscription
            Subscription::create([
                'user_id' => $user->id,
                'plan' => $request->plan,
                'amount' => $request->amount,
                'status' => 'active',
                'started_at' => now(),
                'ends_at' => now()->addMonth(),
                'payment_id' => $request->razorpay_payment_id
            ]);

            return response()->json([
                'success' => true,
                'redirect' => '/dashboard'
            ]);

        } catch (\Exception $e) {

            Log::error("Payment verification failed: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Payment verification failed'
            ]);
        }
    }
}