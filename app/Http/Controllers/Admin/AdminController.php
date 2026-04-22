<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Lead;
use App\Models\Subscription;
use App\Models\User;

class AdminController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Core Growth Metrics
        |--------------------------------------------------------------------------
        */

        $totalLeads = Lead::count();

        $activeTrials = Subscription::where('status', 'trial')->count();

        $paidUsers = Subscription::where('status', 'paid')->count();

        $expiredTrials = Subscription::where('status', 'expired')->count();

        $recentLeads = Lead::latest()->take(5)->get();

        /*
        |--------------------------------------------------------------------------
        | Security Visibility Metrics
        |--------------------------------------------------------------------------
        */

        $today = today();

        $threatAttemptsToday = DB::table('security_events')
            ->whereDate('created_at', $today)
            ->count();

        $recentSecurityEvents = DB::table('security_events')
            ->latest()
            ->take(8)
            ->get()
            ->map(function ($event) {
                $event->created_at = \Carbon\Carbon::parse($event->created_at)
                    ->timezone('Asia/Kolkata')
                    ->format('d M h:i A');

                return $event;
            });

        $unknownIpAlerts = DB::table('security_events')
            ->where('type', 'unknown_ip')
            ->whereDate('created_at', $today)
            ->count();

        $todayEvents = DB::table('security_events')
            ->whereDate('created_at', $today)
            ->get();

        $topAttacker = DB::table('security_events')
            ->select('ip', DB::raw('COUNT(*) as total'))
            ->whereDate('created_at', $today)
            ->whereNotNull('ip')
            ->groupBy('ip')
            ->orderByDesc('total')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Admin Last Login
        |--------------------------------------------------------------------------
        */

        $lastLogin = User::where('is_admin', 1)
            ->latest('last_login')
            ->value('last_login');

        if ($lastLogin) {
            $lastLogin = \Carbon\Carbon::parse($lastLogin)
                ->timezone('Asia/Kolkata')
                ->format('d M Y, h:i A');
        }
        /*
        |--------------------------------------------------------------------------
        | Threat Scoring Engine
        |--------------------------------------------------------------------------
        */

        $weights = [
            'failed_login' => 8,
            'honeypot_hit' => 15,
            'locked_out'   => 20,
            'unknown_ip'   => 25,
        ];

        $threatScore = 0;

        foreach ($todayEvents as $event) {
            $threatScore += $weights[$event->type] ?? 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Risk Level Engine
        |--------------------------------------------------------------------------
        */

        $riskLevel = match (true) {
            $threatScore >= 100 => 'CRITICAL',
            $threatScore >= 60  => 'HIGH',
            $threatScore >= 30  => 'MEDIUM',
            default             => 'LOW',
        };

        /*
        |--------------------------------------------------------------------------
        | AI Recommendations
        |--------------------------------------------------------------------------
        */

        $aiRecommendation = match ($riskLevel) {
            'MEDIUM'   => 'Watch repeated IP attempts.',
            'HIGH'     => 'Rotate admin route and review logs.',
            'CRITICAL' => 'Immediate lockdown recommended.',
            default    => 'Normal monitoring only.',
        };

        /*
        |--------------------------------------------------------------------------
        | View Render
        |--------------------------------------------------------------------------
        */

        return view('admin.intakes.dashboard', compact(
            'totalLeads',
            'activeTrials',
            'paidUsers',
            'expiredTrials',
            'recentLeads',
            'threatAttemptsToday',
            'recentSecurityEvents',
            'lastLogin',
            'unknownIpAlerts',
            'topAttacker',
            'threatScore',
            'riskLevel',
            'aiRecommendation'
        ));
    }
}