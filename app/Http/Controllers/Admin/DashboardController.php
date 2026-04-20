<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Subscription;

class AdminController extends Controller
{
    public function index()
    {
        $totalLeads = Lead::count();

        $activeTrials = Subscription::where('status', 'trial')->count();

        $paidUsers = Subscription::where('status', 'active')->count();

        $expiredTrials = Subscription::where('status', 'expired')->count();

        $recentLeads = Lead::latest()->take(20)->get();

        return view('admin.dashboard', compact(
            'totalLeads',
            'activeTrials',
            'paidUsers',
            'expiredTrials',
            'recentLeads'
        ));
    }
}