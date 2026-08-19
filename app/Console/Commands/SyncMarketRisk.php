<?php

namespace App\Console\Commands;

use App\Models\MarketRiskSnapshot;
use Illuminate\Console\Command;

class SyncMarketRisk extends Command
{
    protected $signature   = 'market-risk:sync {--csv= : Path to nifty500_enriched.csv}';
    protected $description = 'Read latest market risk row from enriched CSV and store snapshot';

    public function handle(): int
    {
        $csvPath = $this->option('csv')
            ?? env('MARKET_RISK_CSV_PATH',
               '/home/beast/windowsfolder/FinAdvisorAI/Nifty500/dataset/nifty500_enriched.csv');

        if (! file_exists($csvPath)) {
            $this->error("CSV not found: {$csvPath}");
            return self::FAILURE;
        }

        $lastLine = $this->lastLine($csvPath);
        if (! $lastLine) {
            $this->error('Could not read last line from CSV');
            return self::FAILURE;
        }

        $fp      = fopen($csvPath, 'r');
        $headers = fgetcsv($fp);
        fclose($fp);

        $headers = array_map('trim', $headers);
        $row     = array_combine($headers, str_getcsv($lastLine));

        $required = [
            'date', 'market_risk_score', 'market_risk_score_smooth',
            'market_risk_label', 'vol_regime', 'dd_regime',
            'market_regime', 'warning_severity', 'warning_text',
        ];

        foreach ($required as $col) {
            if (! isset($row[$col])) {
                $this->error("Missing column in CSV: {$col}");
                return self::FAILURE;
            }
        }

        MarketRiskSnapshot::updateOrCreate(
            ['market_date' => $row['date']],
            [
                'score'            => (float) $row['market_risk_score'],
                'score_smooth'     => (float) $row['market_risk_score_smooth'],
                'label'            => $row['market_risk_label'],
                'vol_regime'       => $row['vol_regime'],
                'dd_regime'        => $row['dd_regime'],
                'market_regime'    => $row['market_regime'],
                'warning_severity' => $row['warning_severity'],
                'warning_text'     => $row['warning_text'],
            ]
        );

        $this->info("✓ Synced market risk snapshot for {$row['date']}");
        $this->info("  Score : {$row['market_risk_score']} | Label: {$row['market_risk_label']}");
        $this->info("  Warning: {$row['warning_text']}");

        return self::SUCCESS;
    }

    private function lastLine(string $path): ?string
    {
        $fp = fopen($path, 'r');
        fseek($fp, -4096, SEEK_END);
        $chunk = fread($fp, 4096);
        fclose($fp);
        $lines = array_filter(explode("\n", trim($chunk)));
        return end($lines) ?: null;
    }
}