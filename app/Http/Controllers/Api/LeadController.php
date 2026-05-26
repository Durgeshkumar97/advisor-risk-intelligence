<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

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

        return response()->json([
            'success' => true,
            'message' => 'Trial request received successfully',
            'data' => $lead
        ], 201);
    }
}
