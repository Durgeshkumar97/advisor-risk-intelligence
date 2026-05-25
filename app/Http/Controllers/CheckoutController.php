<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW CHECKOUT PAGE
    |--------------------------------------------------------------------------
    |
    | Guards:
    |  1. Plan must be active.
    |  2. Authenticated users with an active or trial subscription are shown
    |     an "already subscribed" page instead of the checkout form.
    |     (Guests can always proceed — they will be registered on payment.)
    |
    */

    public function show(string $slug): \Illuminate\View\View
    {
        $plan = Plan::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | ACTIVE SUBSCRIPTION CHECK
        |--------------------------------------------------------------------------
        |
        | If the authenticated user already has an active/trial subscription,
        | redirect to the subscription management page with a helpful message.
        |
        */

        if (Auth::check()) {
            $existing = Subscription::where('user_id', Auth::id())
                ->whereIn('status', ['active', 'trial'])
                ->latest()
                ->first();

            if ($existing) {
                return view('checkout-already-subscribed', [
                    'plan'         => $plan,
                    'subscription' => $existing,
                    'currentPlan'  => $existing->plan,
                ]);
            }
        }

        return view('checkout', [
            'plan'        => $plan,
            'razorpayKey' => config('services.razorpay.key'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT SUCCESS PAGE
    |--------------------------------------------------------------------------
    */

    public function success(Request $request): \Illuminate\View\View
    {
        return view('payment-success');
    }
}
