<?php

namespace App\Console\Commands;

use App\Mail\DailyRiskSignalMail;
use App\Models\Portfolio;
use App\Models\RiskScore;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RiskEngine\PortfolioRiskCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GenerateRiskScore extends Command
{
    protected $signature = 'risk:generate';
    protected $description = 'Generate daily risk scores and send email signals to active subscribers';

    public function handle(PortfolioRiskCalculator $calculator): int
    {
        $this->info('── risk:generate ─────────────────────────────');

        $activeUserIds = Subscription::query()
            ->where(function ($q) {
                $q->where('status', 'active')->where('ends_at', '>', now());
            })
            ->orWhere(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>', now());
            })
            ->pluck('user_id')
            ->unique();

        if ($activeUserIds->isEmpty()) {
            $this->info('No active subscribers.');
            return Command::SUCCESS;
        }

        $users = User::whereIn('id', $activeUserIds)->get();
        $this->info("Processing {$users->count()} active subscriber(s).");

        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $this->processUser($user, $calculator);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('risk:generate — user failed', ['user_id' => $user->id, 'email' => $user->email, 'message' => $e->getMessage()]);
                $this->error("  ✗ {$user->email} — {$e->getMessage()}");
            }
        }

        $this->info("Done. Sent: {$sent} | Failed: {$failed}.");
        return Command::SUCCESS;
    }

    private function processUser(User $user, PortfolioRiskCalculator $calculator): void
    {
        $portfolio = Portfolio::where('user_id', $user->id)->whereHas('assets')->orderByDesc('updated_at')->first();
        $assets = $portfolio ? $portfolio->assets()->get() : collect();

        $result = $calculator->calculate($assets);
        $score = $result['score'];
        $riskLevel = $result['meta']['risk_level'];
        $nextAction = $result['next_action'];

        RiskScore::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio?->id,
            'score' => $score,
            'volatility' => $result['volatility'],
            'drawdown' => $result['drawdown'],
            'generated_at' => now(),
            'meta' => array_merge($result['meta'], ['trigger' => 'daily-cron', 'has_holdings' => $assets->isNotEmpty()]),
        ]);

        if ($assets->isNotEmpty()) {
            Mail::to($user->email)->send(new DailyRiskSignalMail($user, round($score), $riskLevel, $nextAction));
            $this->line("  ✓ {$user->email} — Score: " . round($score) . " ({$riskLevel}) | Assets: {$assets->count()}");
        } else {
            $this->line("  – {$user->email} — no holdings.");
        }
    }
}
