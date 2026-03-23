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
        $validated = $request->validate([
            'advisor_name'   => 'required|string|max:120',
            'email'          => 'required|email|max:200',
            'whatsapp'       => 'required|string|max:20',
            'firm_name'      => 'required|string|max:200',
            'plan'           => 'required|in:starter,pro,team',
            'portfolio_type' => 'required|in:midcap,largecap,debt,multi',
            'main_concern'   => 'nullable|string|max:1000',
        ]);

        $submissionId = Str::uuid();

        $planLabels = [
            'starter' => 'Starter — ₹799/mo',
            'pro'     => 'Pro — ₹1,499/mo',
            'team'    => 'Team — ₹3,499/mo',
        ];

        $portfolioLabels = [
            'midcap'   => 'Mid-cap equity',
            'largecap' => 'Large-cap equity',
            'debt'     => 'Debt / hybrid',
            'multi'    => 'Multi-asset (all three)',
        ];

        ClientIntake::create([
            'submission_uuid' => $submissionId,
            'name'            => $validated['advisor_name'],
            'email'           => $validated['email'],
            'phone'           => $validated['whatsapp'],
            'portfolio_value' => $portfolioLabels[$validated['portfolio_type']],
            'objective'       => 'IFA Trial Subscription — ' . $planLabels[$validated['plan']],
            'horizon'         => 'Ongoing subscription',
            'archetype'       => 'IFA Advisor',
            'concern'         => $validated['main_concern'] ?? 'No specific concern provided',
            'notes'           => $validated['firm_name'],
            'lead_score'      => 'IFA Lead — ' . strtoupper($validated['plan']),
            'ai_status'       => 'pending',
        ]);

        $emailBody = "New RiskLens Trial Subscription\n";
        $emailBody .= str_repeat('─', 46) . "\n\n";
        $emailBody .= "Submission ID : {$submissionId}\n";
        $emailBody .= "Name          : {$validated['advisor_name']}\n";
        $emailBody .= "Email         : {$validated['email']}\n";
        $emailBody .= "WhatsApp      : {$validated['whatsapp']}\n";
        $emailBody .= "Firm          : {$validated['firm_name']}\n";
        $emailBody .= "Plan selected : {$planLabels[$validated['plan']]}\n";
        $emailBody .= "Portfolio type: {$portfolioLabels[$validated['portfolio_type']]}\n";
        $emailBody .= "Concern       : " . ($validated['main_concern'] ?? 'None') . "\n\n";
        $emailBody .= "ACTION: Add WhatsApp number to Monday morning broadcast list.\n";
        $emailBody .= "First report to be sent this coming Monday at 9:00 AM.\n";

        try {
            Mail::raw(
                $emailBody,
                function ($message) use ($validated, $submissionId) {
                    $message->to('durgeshduklan5@gmail.com')
                            ->subject("New Trial Signup — {$validated['advisor_name']} ({$validated['plan']})");
                }
            );
        } catch (\Exception $e) {
            // Log the error but don't fail the submission — the DB record is saved.
            Log::error('RiskLens signup email failed: ' . $e->getMessage(), [
                'submission_uuid' => $submissionId,
                'email'           => $validated['email'],
            ]);
        }

        return back()->with('success', 'You\'re in. Your first report arrives this Monday at 9am on WhatsApp.');
    }

    /**
     * Legacy general intake (keep for other forms)
     */
    public function submit(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:120',
            'email'           => 'required|email',
            'phone'           => 'nullable|string|max:20',
            'portfolio_value' => 'nullable|string|max:100',
            'objective'       => 'nullable|string|max:200',
            'horizon'         => 'nullable|string|max:100',
            'concern'         => 'nullable|string|max:1000',
        ]);

        $submissionId = Str::uuid();

        ClientIntake::create([
            'submission_uuid' => $submissionId,
            'name'            => $request->name,
            'email'           => $request->email,
            'phone'           => $request->phone,
            'portfolio_value' => $request->portfolio_value,
            'objective'       => $request->objective ?? 'General enquiry',
            'horizon'         => $request->horizon ?? 'NA',
            'archetype'       => 'Prospect',
            'concern'         => $request->concern ?? 'No concern specified',
            'notes'           => null,
            'lead_score'      => 'General Lead',
            'ai_status'       => 'pending',
        ]);

        return back()->with('success', 'Thank you. We will be in touch within 24 hours.');
    }
} 
