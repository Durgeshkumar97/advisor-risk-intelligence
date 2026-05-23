<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\Subscription;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | SUBSCRIPTION + PLAN
        |--------------------------------------------------------------------------
        |
        | Eager-load plan in one query.
        | Never redirect here — the view handles the "no subscription" empty state.
        |
        */

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $plan           = $subscription?->plan;
        $planName       = $plan?->name ?? null;
        $portfolioLimit = $plan?->portfolio_limit ?? 0;

        /*
        |--------------------------------------------------------------------------
        | DAYS REMAINING
        |--------------------------------------------------------------------------
        |
        | Use the model helper (handles active vs. trial correctly).
        |
        */

        $daysLeft = $subscription?->daysRemaining() ?? 0;

        /*
        |--------------------------------------------------------------------------
        | PORTFOLIO USAGE
        |--------------------------------------------------------------------------
        */

        $portfolioCount = Portfolio::where('user_id', $user->id)->count();

        /*
        |--------------------------------------------------------------------------
        | RECENT FILE UPLOADS (last 5)
        |--------------------------------------------------------------------------
        */

        $recentFiles = PortfolioFile::with('portfolio')
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | RISK SCORE
        |--------------------------------------------------------------------------
        |
        | null  = no score generated yet (show empty state in view)
        | 0–100 = actual score
        |
        */

        $risk = RiskScore::where('user_id', $user->id)
            ->latest()
            ->first();

        $riskScore       = $risk ? (float) $risk->score : null;
        $riskGeneratedAt = $risk?->generatedTimestamp();

        $riskLevel = match (true) {
            $riskScore === null  => 'NONE',
            $riskScore < 30      => 'LOW',
            $riskScore < 70      => 'MEDIUM',
            default              => 'HIGH',
        };

        /*
        |--------------------------------------------------------------------------
        | RECOMMENDATION & NEXT ACTION
        |--------------------------------------------------------------------------
        */

        $recommendation = match ($riskLevel) {
            'LOW'    => 'Portfolio stable. Continue monitoring.',
            'MEDIUM' => 'Review exposure and rebalance selectively.',
            'HIGH'   => 'Immediate portfolio review recommended.',
            default  => 'Upload your portfolio to receive your first risk score.',
        };

        $nextAction = match ($riskLevel) {
            'LOW'    => 'Monitor market movement weekly.',
            'MEDIUM' => 'Reduce concentration risk.',
            'HIGH'   => 'Schedule an immediate portfolio review.',
            default  => 'Upload a CSV, XLSX, or PDF portfolio file to get started.',
        };

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('user.dashboard', [
            'user'            => $user,
            'subscription'    => $subscription,
            'planName'        => $planName,
            'portfolioLimit'  => $portfolioLimit,
            'portfolioCount'  => $portfolioCount,
            'recentFiles'     => $recentFiles,
            'daysLeft'        => $daysLeft,
            'riskScore'       => $riskScore,
            'riskLevel'       => $riskLevel,
            'riskGeneratedAt' => $riskGeneratedAt,
            'recommendation'  => $recommendation,
            'nextAction'      => $nextAction,
        ]);
    }
}
