<?php

namespace App\Services;

use App\Models\ClientIntake;

class ReportService
{
    public function generate(ClientIntake $user): string
    {
        // Dummy logic (replace later with real market data)

        $riskScore = rand(4, 8);

        $riskLevel = match (true) {
            $riskScore >= 7 => 'HIGH',
            $riskScore >= 5 => 'MEDIUM',
            default => 'LOW',
        };

        return "
Weekly Risk Report

Name: {$user->name}

Risk Score: {$riskScore}
Risk Level: {$riskLevel}

Top Risks:
- FII outflows increasing
- Rupee volatility
- Mid-cap valuations elevated

Client Script:
'Markets are slightly volatile this week. Stay invested, avoid panic.'

— RiskLens
        ";
    }
}
