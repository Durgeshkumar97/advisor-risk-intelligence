<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Subscription;
use App\Models\Plan;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Active subscription
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('plan')
            ->latest()
            ->first();

        // Defaults
        $planName = 'Free';
        $expiryDate = null;
        $daysLeft = 0;

        if ($subscription && $subscription->plan) {
            $planName = $subscription->plan->name;
            $expiryDate = $subscription->ends_at;
            $daysLeft = now()->diffInDays($expiryDate, false);
        }

        /*
        |--------------------------------------------------------------------------
        | FAKE VALUE (FOR NOW) → replace later with real engine
        |--------------------------------------------------------------------------
        */

        $riskScore = rand(10, 80);
        $riskLevel = $riskScore > 60 ? 'HIGH' : ($riskScore > 30 ? 'MEDIUM' : 'LOW');

        $recommendation = match ($riskLevel) {
            'HIGH' => 'Reduce equity exposure immediately.',
            'MEDIUM' => 'Monitor market volatility closely.',
            default => 'All conditions stable.'
        };

        /*
        |--------------------------------------------------------------------------
        | NEXT ACTION (RETENTION DRIVER)
        |--------------------------------------------------------------------------
        */

        $nextAction = $daysLeft <= 3
            ? 'Renew your plan to avoid interruption.'
            : 'Check today’s risk signals.';

        return view('user.dashboard', compact(
            'planName',
            'expiryDate',
            'daysLeft',
            'riskScore',
            'riskLevel',
            'recommendation',
            'nextAction'
        ));
    }
}