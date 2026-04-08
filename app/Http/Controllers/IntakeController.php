<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ClientIntake;

class IntakeController extends Controller
{
    private array $portfolioLabels = [
        'midcap'   => 'Mid-cap equity',
        'largecap' => 'Large-cap equity',
        'debt'     => 'Debt / hybrid',
        'multi'    => 'Multi-asset',
    ];

    public function ifaSubmit(Request $request)
    {
        Log::info('IFA Form Hit', $request->all());

        // Validation
        $validated = $request->validate([
            'advisor_name'   => 'required|string|max:120',
            'email'          => 'required|email|max:200',
            'whatsapp'       => 'required|string|max:20',
            'firm_name'      => 'required|string|max:200',
            'portfolio_type' => 'required|in:midcap,largecap,debt,multi',
        ]);

        //Prevent duplicate
        $exists = ClientIntake::where('email', $validated['email'])
            ->orWhere('whatsapp', $validated['whatsapp'])
            ->first();

        if ($exists) {
            return back()->with('success', 'Already registered.');
        }

        $submissionId = (string) Str::uuid();

        //Save clean trial data
        ClientIntake::create([
            'submission_uuid' => $submissionId,

            'name'     => $validated['advisor_name'],
            'email'    => $validated['email'],
            'whatsapp' => $validated['whatsapp'],

            // CORRECT MODEL
            'plan'   => null,
            'status' => 'trial',

            'trial_started_at' => now(),
            'trial_ends_at'   => now()->addDays(14),

            'portfolio_type'  => $validated['portfolio_type'],
            'portfolio_value' => $this->portfolioLabels[$validated['portfolio_type']],

            // Simple lead scoring (can upgrade later)
            'lead_score' => 50,

            'ai_status' => 'pending',

            'notes' => $validated['firm_name'],
        ]);

        // Better email (structured)
        $this->sendSignupNotification($submissionId, $validated);

        return back()->with('success', 'Trial started successfully.');
    }

    private function sendSignupNotification(string $submissionId, array $validated): void
    {
        $portfolio = $this->portfolioLabels[$validated['portfolio_type']];

        $body  = "🚀 New Trial Signup\n\n";
        $body .= "ID: {$submissionId}\n";
        $body .= "Name: {$validated['advisor_name']}\n";
        $body .= "Email: {$validated['email']}\n";
        $body .= "WhatsApp: {$validated['whatsapp']}\n";
        $body .= "Firm: {$validated['firm_name']}\n";
        $body .= "Portfolio: {$portfolio}\n";
        $body .= "Status: Trial Started\n";

        try {
            Mail::raw($body, function ($message) use ($validated) {
                $message->to(config('mail.admin_email') ?? $validated['email'])
                    ->subject("New Trial — {$validated['advisor_name']}");
            });
        } catch (\Exception $e) {
            Log::error('Email failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}