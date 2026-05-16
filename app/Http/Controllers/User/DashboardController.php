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
        | SUBSCRIPTION
        |--------------------------------------------------------------------------
        */

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription) {
            return redirect('/pricing')
                ->with('error', 'No active subscription found.');
        }

        /*
        |--------------------------------------------------------------------------
        | PLAN
        |--------------------------------------------------------------------------
        */

        $planName = $subscription->plan->name ?? 'No Plan';

        /*
        |--------------------------------------------------------------------------
        | EXPIRY
        |--------------------------------------------------------------------------
        */

        $expiryDate = $subscription->ends_at;

        $daysLeft = 0;

        if ($expiryDate) {
            $daysLeft = max(
                0,
                now()->diffInDays($expiryDate)
            );
        }

        /*
        |--------------------------------------------------------------------------
        | RISK SCORE
        |--------------------------------------------------------------------------
        */

        $risk = RiskScore::where('user_id', $user->id)
            ->latest()
            ->first();

        $riskScore = $risk->score ?? 0;

        /*
        |--------------------------------------------------------------------------
        | RISK LEVEL
        |--------------------------------------------------------------------------
        */

        $riskLevel = match (true) {
            $riskScore < 30 => 'LOW',
            $riskScore < 70 => 'MEDIUM',
            default => 'HIGH',
        };

        /*
        |--------------------------------------------------------------------------
        | RECOMMENDATION
        |--------------------------------------------------------------------------
        */

        $recommendation = match ($riskLevel) {
            'LOW' => 'Portfolio stable. Continue monitoring.',
            'MEDIUM' => 'Review exposure and rebalance selectively.',
            'HIGH' => 'Immediate portfolio review recommended.',
        };

        /*
        |--------------------------------------------------------------------------
        | NEXT ACTION
        |--------------------------------------------------------------------------
        */

        $nextAction = match ($riskLevel) {
            'LOW' => 'Monitor market movement weekly.',
            'MEDIUM' => 'Reduce concentration risk.',
            'HIGH' => 'Schedule immediate portfolio review.',
        };

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('user.dashboard', [
            'user' => $user,
            'subscription' => $subscription,
            'planName' => $planName,
            'expiryDate' => $expiryDate,
            'daysLeft' => $daysLeft,
            'riskScore' => $riskScore,
            'riskLevel' => $riskLevel,
            'recommendation' => $recommendation,
            'nextAction' => $nextAction,
        ]);
    }
}
