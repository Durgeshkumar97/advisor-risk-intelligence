<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CompletePaymentAction;
use App\Actions\Payments\CreateRazorpayOrderAction;
use App\Actions\Payments\VerifyRazorpayPaymentAction;

use App\Models\Payment;
use App\Models\Plan;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CREATE ORDER
    |--------------------------------------------------------------------------
    */

    public function create(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'plan'  => 'required|string|exists:plans,slug',
        ]);

        try {

            $plan = Plan::where(
                'slug',
                $validated['plan']
            )->firstOrFail();

            $payment = app(CreateRazorpayOrderAction::class)
                ->execute($plan, $validated);

            return response()->json([
                'success' => true,
                'order_id' => $payment->order_id,
                'amount' => (int) round($payment->amount * 100),
                'currency' => 'INR',
                'key' => config('services.razorpay.key'),
            ]);

        } catch (\Throwable $e) {

            Log::error('Payment order creation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY PAYMENT
    |--------------------------------------------------------------------------
    */

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        try {

            $payment = app(VerifyRazorpayPaymentAction::class)
                ->execute($validated);

            app(CompletePaymentAction::class)
                ->execute($payment);

            return response()->json([
                'success'  => true,
                'redirect' => route('payment.success'),
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
            ], 422);

        } catch (\Throwable $e) {

            Log::error('Payment verification failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification error.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT FAILED
    |--------------------------------------------------------------------------
    */

    public function failure(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|string',
        ]);

        try {

            $payment = Payment::where(
                'order_id',
                $validated['order_id']
            )->first();

            if ($payment && $payment->status === 'pending') {

                $payment->update([
                    'status' => 'failed',
                ]);
            }

            return response()->json([
                'success' => true,
            ]);

        } catch (\Throwable $e) {

            Log::error('Payment failure update failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
            ], 500);
        }
    }
}
