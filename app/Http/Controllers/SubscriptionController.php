<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — show the user current subscription details
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $user         = Auth::user();
        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $plan     = $subscription?->plan;
        $plans    = Plan::where('is_active', true)->orderBy('price')->get();
        $daysLeft = $subscription ? $subscription->daysRemaining() : 0;

        return view('user.subscription', compact('subscription', 'plan', 'plans', 'daysLeft'));
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL — soft-cancel an active or trial subscription
    |--------------------------------------------------------------------------
    */

    public function cancel(Request $request)
    {
        $user         = Auth::user();
        $subscription = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (!$subscription) {
            return redirect()
                ->route('subscription.index')
                ->with('error', 'No active subscription to cancel.');
        }

        $subscription->update(['status' => 'cancelled']);

        $endDate = ($subscription->ends_at ?? $subscription->trial_ends_at)?->format('d M Y');

        return redirect()
            ->route('subscription.index')
            ->with('success', 'Subscription cancelled. You retain access until ' . $endDate . '.');
    }
}
