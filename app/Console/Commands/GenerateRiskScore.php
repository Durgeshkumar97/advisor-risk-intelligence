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
    protected $signature   = 'risk:generate';
    protected $description = 'Generate daily risk scores and send email signals to active subscribers';

    public function handle(PortfolioRiskCalculator $calculator): int
    {
        $this->info('── risk:generate ─────────────────────────────');

        /*
        |----------------------------------------------------------------------
        | ACTIVE SUBSCRIBER IDS
        |----------------------------------------------------------------------
        |
        | Only users whose subscription is currently live receive signals.
        | Expired, cancelled, and no-plan users are excluded.
        |
        */

        $activeUserIds = Subscription::query()
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->where('ends_at', '>', now());
            })
            ->orWhere(function ($q) {
                $q->where('status', 'trial')
                    ->where('trial_ends_at', '>', now());
            })
            ->pluck('user_id')
            ->unique();

        if ($activeUserIds->isEmpty()) {
            $this->info('No active subscribers. Exiting.');
            return Command::SUCCESS;
        }

        $users = User::whereIn('id', $activeUserIds)->get();
        $this->info("Processing {$users->count()} active subscriber(s).");

        $sent   = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $this->processUser($user, $calculator);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('risk:generate — user failed.', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
                $this->error("  ✗ {$user->email} — {$e->getMessage()}");
            }
        }

        $this->info("Done. Sent: {$sent} | Failed: {$failed}.");

        Log::info('risk:generate completed.', [
            'sent'   => $sent,
            'failed' => $failed,
            'total'  => $users->count(),
        ]);

        return Command::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | PER-USER PROCESSING
    |--------------------------------------------------------------------------
    */

    private function processUser(User $user, PortfolioRiskCalculator $calculator): void
    {
        /*
        |----------------------------------------------------------------------
        | LOAD PORTFOLIO ASSETS
        |----------------------------------------------------------------------
        |
        | Prefer the user's most recently updated portfolio with assets.
        | If no assets found, fall back to a market-signal-only score.
        |
        */

        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereHas('assets')
            ->orderByDesc('updated_at')
            ->first();

        $assets = $portfolio
            ? $portfolio->assets()->get()
            : collect();

        /*
        |----------------------------------------------------------------------
        | CALCULATE RISK SCORE
        |----------------------------------------------------------------------
        */

        $result = $calculator->calculate($assets);

        $score      = $result['score'];
        $riskLevel  = $result['meta']['risk_level'];
        $nextAction = $result['next_action'];
        $volatility = $result['volatility'];
        $drawdown   = $result['drawdown'];

        /*
        |----------------------------------------------------------------------
        | PERSIST RISK SCORE
        |----------------------------------------------------------------------
        */

        RiskScore::create([
            'user_id'      => $user->id,
            'portfolio_id' => $portfolio?->id,
            'score'        => $score,
            'volatility'   => $volatility,
            'drawdown'     => $drawdown,
            'generated_at' => now(),
            'meta'         => array_merge($result['meta'], [
                'trigger'     => 'daily-cron',
                'next_action' => $nextAction,
                'risk_flags'  => $result['risk_flags'],
                'has_holdings'=> $assets->isNotEmpty(),
            ]),
        ]);

        /*
        |----------------------------------------------------------------------
        | SEND DAILY EMAIL
        |----------------------------------------------------------------------
        |
        | Only send if the user has actual portfolio assets. A score of
        | "NONE" (no holdings) would produce a meaningless email.
        |
        */

        if ($assets->isNotEmpty()) {

            Mail::to($user->email)
                ->send(new DailyRiskSignalMail($user, round($score), $riskLevel, $nextAction));

            Log::info('risk:generate — signal sent.', [
                'user_id'    => $user->id,
                'email'      => $user->email,
                'score'      => $score,
                'risk_level' => $riskLevel,
                'assets'     => $assets->count(),
            ]);

            $this->line(sprintf(
                '  ✓ %s — Score: %d (%s) | Assets: %d | %s',
                $user->email,
                round($score),
                $riskLevel,
                $assets->count(),
                $nextAction
            ));

        } else {

            Log::info('risk:generate — no holdings, email skipped.', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            $this->line("  – {$user->email} — no portfolio holdings yet, email skipped.");
        }
    }
}
