<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Subscription;
use App\Models\RiskScore;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        |BLOCK IF NOT ONBOARDED
        |--------------------------------------------------------------------------
        */
        if (!$user->onboarding_completed) {
            return redirect()->route('onboarding');
        }

        /*
        |--------------------------------------------------------------------------
        |SUBSCRIPTION
        |--------------------------------------------------------------------------
        */
        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        /*
        |--------------------------------------------------------------------------
        | SAFETY FALLBACK
        |--------------------------------------------------------------------------
        */
        if (!$subscription) {
            return redirect()->route('pricing')
                ->with('error', 'No active subscription found.');
        }

        /*
        |--------------------------------------------------------------------------
        | EXPIRY
        |--------------------------------------------------------------------------
        */
        $daysLeft = $subscription->ends_at
            ? now()->diffInDays($subscription->ends_at, false)
            : 0;

        /*
        |--------------------------------------------------------------------------
        |RISK SCORE (VALUE DELIVERY)
        |--------------------------------------------------------------------------
        */
        $risk = RiskScore::where('user_id', $user->id)
            ->latest()
            ->first();

        $riskScore = $risk->score ?? 42;

        $riskLevel = match (true) {
            $riskScore < 30 => 'LOW',
            $riskScore < 70 => 'MEDIUM',
            default => 'HIGH'
        };

        /*
        |--------------------------------------------------------------------------
        | NEXT ACTION ENGINE
        |--------------------------------------------------------------------------
        */
        $nextAction = match (true) {
            $riskScore > 70 => 'Reduce equity exposure',
            $daysLeft < 3 => 'Renew your plan',
            default => 'Review today’s report'
        };

        return view('user.dashboard', [
            'user' => $user,
            'subscription' => $subscription,
            'plan' => $subscription->plan,
            'daysLeft' => $daysLeft,
            'riskScore' => $riskScore,
            'riskLevel' => $riskLevel,
            'nextAction' => $nextAction
        ]);
    }
}