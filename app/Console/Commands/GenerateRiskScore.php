<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\DailyRiskSignalMail;
use App\Models\User;
use App\Models\RiskScore;

class GenerateRiskScore extends Command
{
    protected $signature = 'risk:generate';

    protected $description = 'Generate daily risk scores and send emails';

    public function handle()
    {
        $users = User::all();

        foreach ($users as $user) {

            /*
            |--------------------------------------------------------------------------
            | GENERATE SCORE
            |--------------------------------------------------------------------------
            */

            $score = rand(20, 90);

            RiskScore::create([
                'user_id' => $user->id,
                'score' => $score,
                'meta' => [
                    'source' => 'daily-engine'
                ]
            ]);

            /*
            |--------------------------------------------------------------------------
            | DETERMINE RISK LEVEL
            |--------------------------------------------------------------------------
            */

            $riskLevel = match (true) {
                $score < 30 => 'LOW',
                $score < 70 => 'MEDIUM',
                default => 'HIGH'
            };

            /*
            |--------------------------------------------------------------------------
            | NEXT ACTION
            |--------------------------------------------------------------------------
            */

            $nextAction = match (true) {
                $score > 70 => 'Reduce aggressive exposure',
                $score > 40 => 'Review portfolio allocation',
                default => 'Maintain current strategy'
            };

            /*
            |--------------------------------------------------------------------------
            | SEND EMAIL
            |--------------------------------------------------------------------------
            */

            Mail::to($user->email)
                ->send(new DailyRiskSignalMail(
                    $user,
                    $score,
                    $riskLevel,
                    $nextAction
                ));

            $this->info("Sent risk signal to {$user->email}");
        }

        return Command::SUCCESS;
    }
}