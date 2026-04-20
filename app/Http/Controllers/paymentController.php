<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Subscription;

class PaymentController extends Controller
{
    /**
     * Upgrade user manually (MVP version before Razorpay)
     *
     * URL Example:
     * /upgrade/pro?phone=9111111111
     */
    public function upgrade($planSlug, Request $request)
    {
        // 1. Validate phone input
        $request->validate([
            'phone' => 'required|min:10|max:15'
        ]);

        // 2. Find Lead
        $lead = Lead::where('phone', $request->phone)->first();

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found'
            ], 404);
        }

        // 3. Find Plan
        $plan = Plan::where('slug', $planSlug)->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }

        // 4. Find Subscription
        $subscription = Subscription::where('lead_id', $lead->id)->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found'
            ], 404);
        }

        // 5. Upgrade subscription
        $subscription->update([
            'plan_id'   => $plan->id,
            'status'    => 'active',
            'starts_at' => now(),
            'ends_at'   => now()->addMonth()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan upgraded successfully',
            'data' => [
                'user' => $lead->name,
                'phone' => $lead->phone,
                'plan' => $plan->name,
                'status' => 'active',
                'valid_till' => now()->addMonth()->toDateTimeString()
            ]
        ]);
    }

    /**
     * Future Razorpay verification webhook
     */
    public function verify(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Payment verification route ready for Razorpay'
        ]);
    }
}