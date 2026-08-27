<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        | NO SUBSCRIPTION IS CREATED HERE
        |----------------------------------------------------------------------
        |
        | This used to insert a Subscription carrying only lead_id, with no
        | user_id — but subscriptions.user_id is NOT NULL, so the insert threw
        | and EVERY first-time caller received a 500. The endpoint has never
        | successfully started a trial.
        |
        | Making the column nullable would not fix it either: CheckSubscription
        | and EnsureActiveSubscription both resolve by user_id, and nothing
        | anywhere maps lead_id back to a user, so the row would grant no
        | access. The response also sends the caller to /login, where there is
        | no account to sign into — this flow never creates a User, and cannot,
        | since email is optional here.
        |
        | So it captures the lead (which the admin panel does consume) and says
        | only what is true. Actually provisioning a trial account needs a
        | product decision about identity — phone-only signup, or requiring an
        | email — and is deliberately not invented here.
        |
        */

        Log::info('LeadController: trial lead captured; no subscription provisioned.', [
            'lead_id' => $lead->id,
            'plan' => $plan->slug,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Details received. Our team will contact you shortly to set up your account.',
            'redirect_url' => route('login'),
        ]);
    }
}
