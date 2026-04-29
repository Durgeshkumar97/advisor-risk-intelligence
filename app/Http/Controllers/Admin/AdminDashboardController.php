<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Subscription;

class AdminDashboardController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Core Dashboard Metrics
        |--------------------------------------------------------------------------
        */

        $totalLeads = Lead::count();

        $activeTrials = Subscription::where('status', 'trial')->count();

        $paidUsers = Subscription::where('status', 'active')->count();

        $expiredTrials = Subscription::where('status', 'expired')->count();

        $recentLeads = Lead::latest()
            ->take(20)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Render Admin Dashboard
        |--------------------------------------------------------------------------
        */

        return view('admin.dashboard', compact(
            'totalLeads',
            'activeTrials',
            'paidUsers',
            'expiredTrials',
            'recentLeads'
        ));
    }
}