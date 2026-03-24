<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ClientIntake;

class IntakeController extends Controller
{
    private array $planLabels = [
        'starter' => 'Starter — ₹799/mo',
        'pro'     => 'Pro — ₹1,499/mo',
        'team'    => 'Team — ₹3,499/mo',
    ];

    private array $portfolioLabels = [
        'midcap'   => 'Mid-cap equity',
        'largecap' => 'Large-cap equity',
        'debt'     => 'Debt / hybrid',
        'multi'    => 'Multi-asset',
    ];

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

        // Deduplication (critical)
        $exists = ClientIntake::where('email', $validated['email'])
            ->orWhere('phone', $validated['whatsapp'])
            ->exists();

        if ($exists) {
            return back()->with('success', 'Already registered. We will contact you.');
        }

        $submissionId = (string) Str::uuid();

        ClientIntake::create([
            'submission_uuid' => $submissionId,
            'name'            => $validated['advisor_name'],
            'email'           => $validated['email'],
            'phone'           => $validated['whatsapp'],

            // store raw + label (important for analytics)
            'portfolio_type'  => $validated['portfolio_type'],
            'portfolio_value' => $this->portfolioLabels[$validated['portfolio_type']],

            'objective'       => 'IFA Trial — ' . $this->planLabels[$validated['plan']],
            'horizon'         => 'Ongoing subscription',
            'archetype'       => 'IFA Advisor',
            'concern'         => $validated['main_concern'] ?? null,
            'notes'           => $validated['firm_name'],

            // numeric scoring (important)
            'lead_score'      => $this->getLeadScore($validated['plan']),

            'ai_status'       => 'pending',
        ]);

        // async email (non-blocking)
        dispatch(function () use ($submissionId, $validated) {
            $this->sendSignupNotification($submissionId, $validated);
        });

        return back()->with('success', 'You\'re in. First report arrives Monday 9 AM on WhatsApp.');
    }

    public function submit(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:120',
            'email'           => 'required|email|max:200',
            'phone'           => 'nullable|string|max:20',
            'portfolio_value' => 'nullable|string|max:100',
            'objective'       => 'nullable|string|max:200',
            'horizon'         => 'nullable|string|max:100',
            'concern'         => 'nullable|string|max:1000',
        ]);

        ClientIntake::create([
            'submission_uuid' => (string) Str::uuid(),
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'phone'           => $validated['phone'],
            'portfolio_value' => $validated['portfolio_value'],
            'objective'       => $validated['objective'] ?? 'General enquiry',
            'horizon'         => $validated['horizon'],
            'archetype'       => 'Prospect',
            'concern'         => $validated['concern'],
            'notes'           => null,
            'lead_score'      => 10,
            'ai_status'       => 'pending',
        ]);

        return back()->with('success', 'Thank you. We will contact you within 24 hours.');
    }

    private function getLeadScore(string $plan): int
    {
        return match ($plan) {
            'team' => 90,
            'pro' => 70,
            'starter' => 50,
        };
    }

    private function sendSignupNotification(string $submissionId, array $validated): void
    {
        $plan      = $this->planLabels[$validated['plan']];
        $portfolio = $this->portfolioLabels[$validated['portfolio_type']];

        $body  = "New Trial Signup\n";
        $body .= "----------------------------------------\n\n";
        $body .= "ID        : {$submissionId}\n";
        $body .= "Name      : {$validated['advisor_name']}\n";
        $body .= "Email     : {$validated['email']}\n";
        $body .= "WhatsApp  : {$validated['whatsapp']}\n";
        $body .= "Firm      : {$validated['firm_name']}\n";
        $body .= "Plan      : {$plan}\n";
        $body .= "Portfolio : {$portfolio}\n";
        $body .= "Concern   : " . ($validated['main_concern'] ?? 'None') . "\n\n";
        $body .= "ACTION: Add to WhatsApp broadcast list.\n";

        try {
            Mail::raw($body, function ($message) use ($validated, $plan) {
                $message
                    ->to(config('mail.admin_email')) 
                    ->subject("New signup — {$validated['advisor_name']} ({$plan})");
            });
        } catch (\Exception $e) {
            Log::error('Signup email failed', [
                'submission_uuid' => $submissionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}