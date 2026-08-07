<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:100',
            'phone' => 'required|string|min:10|max:15',
            'email' => 'nullable|email|max:150',
        ]);

        /*
        |----------------------------------------------------------------------
        | IDEMPOTENCY — return existing trial immediately
        |----------------------------------------------------------------------
        */

        $existingLead = Lead::where('phone', $request->phone)->first();

        if ($existingLead) {
            return response()->json([
                'success' => true,
                'message' => 'Existing trial found',
                'redirect_url' => route('login'),
            ]);
        }

        /*
        |----------------------------------------------------------------------
        | GUARD — starter plan must be seeded before trials can be created
        |----------------------------------------------------------------------
        |
        | BUG #7 FIX: previously $plan->id would throw a fatal null-pointer
        | error if the PlanSeeder had not been run.
        |
        */

        $plan = Plan::where('slug', 'starter')->first();

        if (! $plan) {
            return response()->json([
                'success' => false,
                'message' => 'Trial plan is not available at this time. Please contact support.',
            ], 503);
        }

        /*
        |----------------------------------------------------------------------
        | CREATE LEAD
        |----------------------------------------------------------------------
        */

        $lead = Lead::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'selected_plan' => 'starter',
            'source' => 'landing_page',
            'status' => 'new',
        ]);

        /*
        |----------------------------------------------------------------------
        | CREATE 7-DAY TRIAL SUBSCRIPTION
        |----------------------------------------------------------------------
        */

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
            'redirect_url' => route('login'),
        ], 201);
    }
}
