<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — subscription management page
    |--------------------------------------------------------------------------
    */

    public function index(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
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
    | CANCEL — soft cancel (marks status cancelled, access continues till ends_at)
    |--------------------------------------------------------------------------
    */

    public function cancel(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        $subscription = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        if (!$subscription) {
            return redirect()->route('subscription.index')
                ->with('error', 'No active subscription to cancel.');
        }

        try {
            $subscription->update(['status' => 'cancelled']);

            Log::info('Subscription cancelled by user.', [
                'user_id'         => $user->id,
                'subscription_id' => $subscription->id,
            ]);

            $endDate = ($subscription->ends_at ?? $subscription->trial_ends_at)?->format('d M Y');

            return redirect()->route('subscription.index')
                ->with('success', 'Your subscription has been cancelled. You retain access until ' . $endDate . '.');

        } catch (\Throwable $e) {
            Log::error('Subscription cancellation failed.', [
                'user_id'         => $user->id,
                'subscription_id' => $subscription->id,
                'message'         => $e->getMessage(),
            ]);

            return redirect()->route('subscription.index')
                ->with('error', 'Cancellation failed. Please contact support.');
        }
    }
}
