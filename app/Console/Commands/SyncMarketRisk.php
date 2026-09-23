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
            ?? config('risk.market_risk_csv_path');

        if (! file_exists($csvPath)) {
            $this->error("CSV not found: {$csvPath}");
            $this->line('  Nothing in this command or deploy.sh fetches that file — it has to be put there.');
            $this->line('  See DEPLOY.md, "Market risk sync", for the columns it must contain.');
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

        // warning_severity and warning_text are deliberately NOT required.
        // The producer has never emitted them, and refusing the whole file over
        // two optional annotations meant none of the seven columns that
        // actually drive scoring ever reached the database. Written when
        // present, null when not.
        $required = [
            'date', 'market_risk_score', 'market_risk_score_smooth',
            'market_risk_label', 'vol_regime', 'dd_regime',
            'market_regime',
        ];

        // Every missing column at once, not just the first. Reporting one at a
        // time makes fixing the producer an N-deploy discovery process: you add
        // vol_regime, ship, and only then learn dd_regime was missing too.
        $missing = array_values(array_filter(
            $required,
            fn ($col) => ! isset($row[$col])
        ));

        if ($missing) {
            $this->error('Missing column(s) in CSV: '.implode(', ', $missing));
            $this->line('  Columns present: '.implode(', ', $headers));
            return self::FAILURE;
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
                'warning_severity' => $row['warning_severity'] ?? null,
                'warning_text'     => $row['warning_text'] ?? null,
            ]
        );

        $this->info("✓ Synced market risk snapshot for {$row['date']}");
        $this->info("  Score : {$row['market_risk_score']} | Label: {$row['market_risk_label']}");
        if (! empty($row['warning_text'])) {
            $this->info("  Warning: {$row['warning_text']}");
        }

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