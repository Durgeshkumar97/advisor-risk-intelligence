<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\ClientIntake;

class IntakeController extends Controller
{
    public function ifaSubmit(Request $request)
    {
        $request->validate([
            'advisor_name' => 'required',
            'email' => 'required|email',
            'firm_name' => 'required',
            'clients_managed' => 'required',
            'main_concern' => 'nullable',
            'portfolio_file' => 'required|file|mimes:pdf,xlsx,xls,csv,jpg,jpeg,png,webp|max:10240'
        ]);
 
        $submissionId = Str::uuid();
 
        $file = $request->file('portfolio_file');
        $folder = 'ifa_uploads/' . now()->format('Y-m');
        $filePath = $file->store($folder);
 
        ClientIntake::create([
            'submission_uuid' => $submissionId,
            'name' => $request->advisor_name,
            'email' => $request->email,
            'phone' => null,
            'portfolio_value' => $request->clients_managed,
            'objective' => 'IFA Submission',
            'horizon' => 'NA',
            'archetype' => 'Advisor',
            'concern' => $request->main_concern ?? 'No concern specified',
            'notes' => $request->firm_name,
            'lead_score' => 'IFA Lead',
            'ai_status' => 'pending'
        ]);
 
        try {
            Mail::raw(
                "New Advisor Submission Received:

Submission ID: {$submissionId}
Advisor Name: {$request->advisor_name}
Email: {$request->email}
Firm Name: {$request->firm_name}
Clients Managed: {$request->clients_managed}
Main Concern: " . ($request->main_concern ?? 'No concern specified') . "
Stored File: {$filePath}",
                function ($message) use ($file, $request) {
                    $message->to('durgeshduklan5@gmail.com')
                            ->subject('New Advisor Submission')
                            ->attach(
                                $file->getRealPath(),
                                [
                                    'as' => str_replace(' ', '_', $request->advisor_name) . '_' . $file->getClientOriginalName()
                                ]
                            );
                }
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Mail failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Advisor submission received successfully.');
    }
}

