<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $plan = $subscription?->plan;
        $planName = $plan?->name ?? null;
        $monthlyClientLimit = $plan?->monthly_client_limit ?? 0;
        $monthlyClientCount = PortfolioFile::monthlyClientCount($user->id);
        $monthlyResetDate = now()->addMonthNoOverflow()->startOfMonth()->format('d M Y');
        $expiryDate = $subscription?->ends_at ?? $subscription?->trial_ends_at;

        $daysLeft = $subscription?->daysRemaining() ?? 0;

        $recentFiles = PortfolioFile::with('portfolio')
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $risk = RiskScore::where('user_id', $user->id)
            ->latest()
            ->first();

        $riskScore = $risk ? (float) $risk->score : null;
        $riskGeneratedAt = $risk?->generatedTimestamp();

        $riskLevel = $riskScore === null
            ? 'NONE'
            : RiskScore::levelFromScore($riskScore);

        // How many equity holdings fell back to AssetRiskScorer's flat
        // category default because the live classifier was unreachable. Not an
        // error — a transparency note, so the advisor knows the score used
        // standard rather than enhanced classification.
        $stockRiskFallbackCount = (int) ($risk?->meta['stock_risk_fallback_count'] ?? 0);

        // Observational only — same rule as the PDF/email surfaces:
        // PortfolioRiskCalculator::buildNextAction() is the single source of
        // this text, computed and stored in meta.next_action at scoring time.
        // Do not re-derive from $riskLevel here; a level-keyed match() cannot
        // reflect which risk flags actually drove the score, and previously
        // asserted prescriptive advice unlinked to the evidence (F-15).
        $nextAction = $risk?->meta['next_action']
            ?? 'Upload your portfolio to receive your first risk score.';

        $portfolios = Portfolio::where('user_id', $user->id)
            ->latest()
            ->get();

        return view('user.dashboard', [
            'user' => $user,
            'subscription' => $subscription,
            'planName' => $planName,
            'monthlyClientLimit' => $monthlyClientLimit,
            'monthlyClientCount' => $monthlyClientCount,
            'monthlyResetDate' => $monthlyResetDate,
            'expiryDate' => $expiryDate,
            'recentFiles' => $recentFiles,
            'daysLeft' => $daysLeft,
            'riskScore' => $riskScore,
            'riskLevel' => $riskLevel,
            'riskGeneratedAt' => $riskGeneratedAt,
            'stockRiskFallbackCount' => $stockRiskFallbackCount,
            'nextAction' => $nextAction,
            'portfolios' => $portfolios,
        ]);
    }
}
