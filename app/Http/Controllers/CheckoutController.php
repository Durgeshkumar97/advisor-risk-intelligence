<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\User;

class CheckoutController extends Controller
{
    public function success(Request $request)
    {
        $orderId = $request->get('order_id');

        if (!$orderId) {
            return redirect()->route('pricing')
                ->with('error', 'Invalid payment session.');
        }

        /*
        |--------------------------------------------------------------------------
        |WAIT FOR WEBHOOK (HANDLE RACE CONDITION)
        |--------------------------------------------------------------------------
        */

        $payment = null;

        for ($i = 0; $i < 5; $i++) {
            $payment = Payment::where('order_id', $orderId)
                ->where('status', 'paid')
                ->first();

            if ($payment) break;

            sleep(1); // wait for webhook
        }

        if (!$payment) {
            Log::warning("Payment not confirmed yet", ['order_id' => $orderId]);

            return redirect()->route('pricing')
                ->with('error', 'Payment is being processed. Please try again.');
        }

        if (!$payment->user_id) {
            Log::error("Payment has no user_id", ['order_id' => $orderId]);

            return redirect()->route('login')
                ->with('error', 'Account setup issue. Contact support.');
        }

        $user = User::find($payment->user_id);

        if (!$user) {
            Log::error("User not found for payment", ['order_id' => $orderId]);

            return redirect()->route('login');
        }

        /*
        |--------------------------------------------------------------------------
        |AUTO LOGIN
        |--------------------------------------------------------------------------
        */

        Auth::login($user);
        $request->session()->regenerate();

        $user->update([
            'last_login_at' => now()
        ]);

        /*
        |--------------------------------------------------------------------------
        |ONBOARDING FLOW
        |--------------------------------------------------------------------------
        */

        if (!$user->onboarding_completed) {
            return redirect()->route('onboarding');
        }

        /*
        |--------------------------------------------------------------------------
        |FINAL DESTINATION
        |--------------------------------------------------------------------------
        */

        return redirect()->route('dashboard')
            ->with('success', 'Welcome! Your subscription is active.');
    }
}