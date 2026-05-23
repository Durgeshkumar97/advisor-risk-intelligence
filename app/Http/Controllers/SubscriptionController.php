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
        $user = Auth::user();

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription) {
            return redirect()->route('pricing')
                ->with('error', 'No subscription found. Choose a plan to get started.');
        }

        return view('user.subscription', [
            'user'         => $user,
            'subscription' => $subscription,
            'plan'         => $subscription->plan,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL — soft cancel (marks ends_at as now, status stays active till then)
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

            /*
            |------------------------------------------------------------------
            | Soft cancel: subscription keeps running until ends_at,
            | but we flag it so renewal does not auto-trigger.
            | We set status = 'cancelled' to distinguish from 'expired'.
            |------------------------------------------------------------------
            */

            $subscription->update([
                'status' => 'cancelled',
            ]);

            Log::info('Subscription cancelled by user.', [
                'user_id'         => $user->id,
                'subscription_id' => $subscription->id,
            ]);

            return redirect()->route('subscription.index')
                ->with('success', 'Your subscription has been cancelled. You retain access until ' . optional($subscription->ends_at)->format('d M Y') . '.');

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
