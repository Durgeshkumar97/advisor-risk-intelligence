<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Subscription;
use App\Models\Plan;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Load Plans (Single Source of Truth)
        |--------------------------------------------------------------------------
        */

        $plans = Plan::pluck('price', 'slug');

        $starterPrice = $plans['starter'] ?? 999;
        $proPrice     = $plans['pro'] ?? 2499;
        $teamPrice    = $plans['team'] ?? 4999;

        /*
        |--------------------------------------------------------------------------
        | Core Metrics
        |--------------------------------------------------------------------------
        */

        $totalLeads = Lead::count();

        $activeTrials = Subscription::where('status', 'trial')->count();

        $paidUsers = Subscription::where('status', 'active')->count();

        $expiredUsers = Subscription::where('status', 'expired')->count();

        /*
        |--------------------------------------------------------------------------
        | REAL MRR (Plan Based) ✅
        |--------------------------------------------------------------------------
        */

        $mrr = Subscription::where('subscriptions.status', 'active')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price');

        $arr = $mrr * 12;

        /*
        |--------------------------------------------------------------------------
        | Pipeline + Conversion
        |--------------------------------------------------------------------------
        */

        $pipelineMRR = $totalLeads * $starterPrice;

        $conversion = $totalLeads > 0
            ? round(($paidUsers / $totalLeads) * 100)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Renewal Risk (REAL)
        |--------------------------------------------------------------------------
        */

        $expiringSoon = Subscription::where('status', 'active')
            ->where('ends_at', '<=', Carbon::now()->addDays(7))
            ->count();

        $renewalValue = Subscription::where('status', 'active')
            ->where('ends_at', '<=', Carbon::now()->addDays(7))
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price');

        /*
        |--------------------------------------------------------------------------
        | Economics (Keep configurable later)
        |--------------------------------------------------------------------------
        */

        $cac = 320;
        $ltv = $mrr > 0 ? $mrr * 8 : 0; // simple multiplier
        $ltvCac = $cac > 0 ? round($ltv / $cac, 1) : 0;

        $runwayMonths = 17;

        /*
        |--------------------------------------------------------------------------
        | Security / AI Layer (placeholder)
        |--------------------------------------------------------------------------
        */

        $threatScore = 12;
        $riskLevel   = 'LOW';

        $riskColor = match ($riskLevel) {
            'LOW' => '#22c55e',
            'MEDIUM' => '#facc15',
            'HIGH' => '#f97316',
            default => '#ef4444',
        };

        $topAttacker = null;
        $aiRecommendation = 'All systems stable.';
        $threatAttemptsToday = 0;
        $unknownIpAlerts = 0;
        $lastLogin = null;

        /*
        |--------------------------------------------------------------------------
        | Data Lists
        |--------------------------------------------------------------------------
        */

        $recentLeads = Lead::latest()->take(20)->get();

        $recentSecurityEvents = collect();

        /*
        |--------------------------------------------------------------------------
        | Final Render
        |--------------------------------------------------------------------------
        */

        return view('admin.dashboard', compact(
            'starterPrice',
            'proPrice',
            'teamPrice',

            'totalLeads',
            'activeTrials',
            'paidUsers',
            'expiredUsers',

            'mrr',
            'arr',
            'pipelineMRR',
            'conversion',

            'expiringSoon',
            'renewalValue',

            'cac',
            'ltv',
            'ltvCac',
            'runwayMonths',

            'threatScore',
            'riskLevel',
            'riskColor',
            'topAttacker',
            'aiRecommendation',
            'threatAttemptsToday',
            'unknownIpAlerts',
            'lastLogin',

            'recentLeads',
            'recentSecurityEvents'
        ));
    }
}