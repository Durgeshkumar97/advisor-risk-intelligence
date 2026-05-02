<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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

    // CREATE ORDER
    public function create(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'phone' => 'required',
            'plan'  => 'required'
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();

        try {

            $order = $this->api->order->create([
                'receipt' => Str::uuid(),
                'amount' => $plan->price * 100, // paise
                'currency' => 'INR'
            ]);

            Payment::create([
                'order_id' => $order['id'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'plan' => $plan->slug,
                'amount' => $plan->price * 100,
                'status' => 'created'
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order['id'],
                'amount' => $plan->price * 100,
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

    // VERIFY (UX ONLY)
    public function verify(Request $request)
    {
        try {

            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ]);

            return response()->json([
                'success' => true,
                'redirect' => '/processing'
            ]);

        } catch (\Exception $e) {

            Log::error("Verify failed: ".$e->getMessage());

            return response()->json([
                'success' => false
            ]);
        }
    }
}