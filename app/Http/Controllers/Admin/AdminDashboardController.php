<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Lead;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PortfolioFile;
use App\Models\Subscription;
use App\Models\User;

use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        /*
        |--------------------------------------------------------------------------
        | PLAN PRICES (single source of truth)
        |--------------------------------------------------------------------------
        */

        $plans        = Plan::pluck('price', 'slug');
        $starterPrice = $plans['starter'] ?? 999;
        $proPrice     = $plans['pro']     ?? 2499;
        $teamPrice    = $plans['team']    ?? 4999;

        /*
        |--------------------------------------------------------------------------
        | USER METRICS
        |--------------------------------------------------------------------------
        */

        $totalUsers   = User::count();
        $adminCount   = User::where('is_admin', true)->count();
        $newUsersWeek = User::where('created_at', '>=', now()->subDays(7))->count();

        /*
        |--------------------------------------------------------------------------
        | SUBSCRIPTION METRICS
        |--------------------------------------------------------------------------
        */

        $totalLeads   = Lead::count();
        $activeTrials = Subscription::where('status', 'trial')->count();
        $paidUsers    = Subscription::where('status', 'active')->count();
        $expiredUsers = Subscription::where('status', 'expired')->count();

        /*
        |--------------------------------------------------------------------------
        | REVENUE — MRR / ARR
        |--------------------------------------------------------------------------
        */

        $mrr = (float) Subscription::where('subscriptions.status', 'active')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price');

        $arr          = $mrr * 12;
        $totalRevenue = (float) Payment::where('status', Payment::STATUS_PAID)->sum('amount');

        /*
        |--------------------------------------------------------------------------
        | PIPELINE + CONVERSION
        |--------------------------------------------------------------------------
        */

        $pipelineMRR = $totalLeads * $starterPrice;
        $conversion  = $totalLeads > 0
            ? round(($paidUsers / $totalLeads) * 100)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | RENEWAL RISK (next 7 days)
        |--------------------------------------------------------------------------
        */

        $expiringSoon = Subscription::where('status', 'active')
            ->where('ends_at', '<=', Carbon::now()->addDays(7))
            ->count();

        $renewalValue = (float) Subscription::where('status', 'active')
            ->where('ends_at', '<=', Carbon::now()->addDays(7))
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price');

        /*
        |--------------------------------------------------------------------------
        | UNIT ECONOMICS
        |--------------------------------------------------------------------------
        */

        $cac         = 320;
        $ltv         = $mrr > 0 ? $mrr * 8 : 0;
        $ltvCac      = $cac > 0 ? round($ltv / $cac, 1) : 0;
        $runwayMonths = 17;

        /*
        |--------------------------------------------------------------------------
        | PORTFOLIO FILES
        |--------------------------------------------------------------------------
        */

        $totalPortfolioFiles     = PortfolioFile::count();
        $processingFiles         = PortfolioFile::where('status', PortfolioFile::STATUS_PROCESSING)->count();
        $failedFiles             = PortfolioFile::where('status', PortfolioFile::STATUS_FAILED)->count();

        /*
        |--------------------------------------------------------------------------
        | RECENT DATA
        |--------------------------------------------------------------------------
        */

        $recentLeads = Lead::latest()
            ->take(20)
            ->get();

        $recentPayments = Payment::with(['plan', 'user'])
            ->where('status', Payment::STATUS_PAID)
            ->latest('paid_at')
            ->take(8)
            ->get();

        $recentSignups = User::where('is_admin', false)
            ->latest()
            ->take(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | PLAN BREAKDOWN
        |--------------------------------------------------------------------------
        */

        $planBreakdown = Subscription::where('status', 'active')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->selectRaw('plans.name, plans.slug, COUNT(*) as count, SUM(plans.price) as revenue')
            ->groupBy('plans.id', 'plans.name', 'plans.slug')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | SECURITY (placeholder — no security_events table yet)
        |--------------------------------------------------------------------------
        */

        $threatScore          = 0;
        $riskLevel            = 'LOW';
        $riskColor            = '#22c55e';
        $topAttacker          = null;
        $aiRecommendation     = 'All systems stable.';
        $threatAttemptsToday  = 0;
        $unknownIpAlerts      = 0;
        $lastLogin            = Auth()->user()?->last_login_at?->format('d M Y, h:i A');
        $recentSecurityEvents = collect();

        /*
        |--------------------------------------------------------------------------
        | RENDER
        |--------------------------------------------------------------------------
        */

        return view('admin.dashboard', compact(
            'starterPrice', 'proPrice', 'teamPrice',
            'totalUsers', 'adminCount', 'newUsersWeek',
            'totalLeads', 'activeTrials', 'paidUsers', 'expiredUsers',
            'mrr', 'arr', 'totalRevenue', 'pipelineMRR', 'conversion',
            'expiringSoon', 'renewalValue',
            'cac', 'ltv', 'ltvCac', 'runwayMonths',
            'totalPortfolioFiles', 'processingFiles', 'failedFiles',
            'recentLeads', 'recentPayments', 'recentSignups', 'planBreakdown',
            'threatScore', 'riskLevel', 'riskColor',
            'topAttacker', 'aiRecommendation',
            'threatAttemptsToday', 'unknownIpAlerts',
            'lastLogin', 'recentSecurityEvents'
        ));
    }
}
