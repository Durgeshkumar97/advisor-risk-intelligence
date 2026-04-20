<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function index()
    {
        if (Auth::user()->email !== 'durgeshduklan5@email.com') {
            abort(403, 'Unauthorized');
        }

        $totalLeads = Lead::count();
        $activeTrials = Subscription::where('status', 'trial')->count();
        $paidUsers = Subscription::where('status', 'paid')->count();
        $expiredTrials = Subscription::where('status', 'expired')->count();

        $recentLeads = Lead::latest()->take(5)->get();

        return view('Admin.intakes.dashboard', compact(
            'totalLeads',
            'activeTrials',
            'paidUsers',
            'expiredTrials',
            'recentLeads'
        ));
    }
}