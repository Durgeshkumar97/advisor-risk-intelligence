<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ClientIntake;

class IntakeController extends Controller
{ 
    public function ifaSubmit(Request $request)
    {
        Log::info('IFA Form Hit', $request->all());

        // Validation
        $validated = $request->validate([
            'advisor_name' => 'required|string|max:120',
            'email'        => 'required|email|max:200',
            'whatsapp'     => 'required|string|max:20',
            'firm_name'    => 'required|string|max:200',
            'document'     => 'nullable|file|mimes:pdf,png,jpg,jpeg,zip|max:10240',
        ]);

        // Prevent duplicate
        $exists = ClientIntake::where('email', $validated['email'])
            ->orWhere('whatsapp', $validated['whatsapp'])
            ->first();

        if ($exists) {
            return back()->with('success', 'Already registered.');
        }

        // Handle file upload
        $documentPath = null;

        if ($request->hasFile('document')) {

            $file = $request->file('document');

            $filename = time() . '_' . $file->getClientOriginalName();

            // store in PRIVATE folder
            $path = $file->storeAs('private_uploads', $filename);

            $documentPath = $path;
        }

        // Save
        ClientIntake::create([
            'submission_uuid' => (string) Str::uuid(),

            'name'     => $validated['advisor_name'],
            'email'    => $validated['email'],
            'whatsapp' => $validated['whatsapp'],
            'firm_name'=> $validated['firm_name'],

            'document_path' => $documentPath,

            // Trial system
            'plan'   => null,
            'status' => 'trial',

            'trial_started_at' => now(),
            'trial_ends_at'   => now()->addDays(14),

            // Revenue system
            'plan_price' => 0,
            'revenue_generated' => 0,

            'lead_score' => 50,
            'ai_status'  => 'pending',
        ]);

        return back()->with('success', 'Trial started successfully.');
    }
} 
