<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Store new lead / start trial
     */
    public function store(Request $request)
    {
        // Validate Request
        $request->validate([
            'name'  => 'required|string|min:2|max:100',
            'phone' => 'required|string|min:10|max:15',
            'email' => 'nullable|email|max:150',
        ]);

        // Check if phone already exists
        $lead = Lead::where('phone', $request->phone)->first();

        if ($lead) {
            return response()->json([
                'success' => true,
                'message' => 'Existing trial found',
                'data'    => $lead
            ], 200);
        }

        // Create New Lead
        $lead = Lead::create([
            'name'          => $request->name,
            'phone'         => $request->phone,
            'email'         => $request->email,
            'selected_plan' => 'starter',
            'source'        => 'landing_page',
            'status'        => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trial started successfully',
            'data'    => $lead
        ], 201);
    }
}