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

        // Pre-load the most-recent portfolio with assets for every user in one query,
        // avoiding 2N queries (1 portfolio lookup + 1 assets load) inside the loop.
        $portfolios = Portfolio::with('assets')
            ->whereIn('user_id', $activeUserIds)
            ->whereHas('assets')
            ->orderByDesc('updated_at')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                if ($this->processUser($user, $calculator, $portfolios->get($user->id))) {
                    $sent++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('risk:generate — user failed.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("  ✗ {$user->email} — {$e->getMessage()}");
            }
        }

        $this->info("Done. Sent: {$sent} | Skipped: {$skipped} | Failed: {$failed}.");

        Log::info('risk:generate completed.', [
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => $users->count(),
        ]);

        return Command::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | PER-USER PROCESSING
    |--------------------------------------------------------------------------
    */

    /**
     * @return bool true if the user was scored, false if skipped for no holdings
     */
    private function processUser(User $user, PortfolioRiskCalculator $calculator, ?Portfolio $portfolio): bool
    {
        $assets = $portfolio ? $portfolio->assets : collect();

        /*
        |----------------------------------------------------------------------
        | NO HOLDINGS, NO ROW
        |----------------------------------------------------------------------
        |
        | A RiskScore for an empty portfolio records nothing about the user —
        | it is the calculator's zero-asset result, identical for everyone —
        | but it used to be written every day for every active subscriber. An
        | account that never uploaded a file therefore accumulated one row per
        | day indefinitely, which is both meaningless history and, when signups
        | are being abused, unbounded growth driven by whoever is registering.
        |
        | The email was already skipped here for the same reason. The row
        | should have been too.
        |
        */
        if ($assets->isEmpty()) {
            Log::info('risk:generate — no holdings, nothing scored.', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            $this->line("  – {$user->email} — no portfolio holdings yet, nothing scored.");

            return false;
        }

        /*
        |----------------------------------------------------------------------
        | CALCULATE RISK SCORE
        |----------------------------------------------------------------------
        */

        $result = $calculator->calculate($assets);

        $score = $result['score'];
        $riskLevel = $result['meta']['risk_level'];
        $nextAction = $result['next_action'];
        $volatility = $result['volatility'];
        $drawdown = $result['drawdown'];

        /*
        |----------------------------------------------------------------------
        | PERSIST RISK SCORE
        |----------------------------------------------------------------------
        */

        RiskScore::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio?->id,
            'score' => $score,
            'volatility' => $volatility,
            'drawdown' => $drawdown,
            'generated_at' => now(),
            'meta' => array_merge($result['meta'], [
                'trigger' => 'daily-cron',
                'next_action' => $nextAction,
                'risk_flags' => $result['risk_flags'],
                'has_holdings' => $assets->isNotEmpty(),
            ]),
        ]);

        /*
        |----------------------------------------------------------------------
        | SEND DAILY EMAIL
        |----------------------------------------------------------------------
        |
        | Unconditional here: a user with no holdings returned above, before
        | anything was calculated, scored or stored.
        |
        */

        Mail::to($user->email)
            ->send(new DailyRiskSignalMail($user, round($score), $riskLevel, $nextAction));

        Log::info('risk:generate — signal sent.', [
            'user_id' => $user->id,
            'email' => $user->email,
            'score' => $score,
            'risk_level' => $riskLevel,
            'assets' => $assets->count(),
        ]);

        $this->line(sprintf(
            '  ✓ %s — Score: %d (%s) | Assets: %d | %s',
            $user->email,
            round($score),
            $riskLevel,
            $assets->count(),
            $nextAction
        ));

        return true;
    }
}
