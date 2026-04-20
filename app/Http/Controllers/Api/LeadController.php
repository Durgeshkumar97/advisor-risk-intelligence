<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|min:2|max:100',
            'phone' => 'required|string|min:10|max:15',
            'email' => 'nullable|email|max:150',
        ]);

        // Existing lead check
        $existingLead = Lead::where('phone', $request->phone)->first();

        if ($existingLead) {
            return response()->json([
                'success' => true,
                'message' => 'Existing trial found',
                'data' => $existingLead
            ]);
        }

        // Create lead
        $lead = Lead::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'selected_plan' => 'starter',
            'source' => 'landing_page',
            'status' => 'new',
        ]);

        // Get Starter Plan
        $plan = Plan::where('slug', 'starter')->first();

        // Create Trial Subscription
        Subscription::create([
            'lead_id' => $lead->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays(7),
            'renewal_at' => now()->addDays(7),
        ]);

        return response()->json([
            'success' => true,
            'message' => '7-day trial started successfully',
            'data' => $lead
        ], 201);
    }
}