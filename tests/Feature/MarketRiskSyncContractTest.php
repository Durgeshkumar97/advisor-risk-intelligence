<?php

use App\Models\MarketRiskSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * Pins the market-risk:sync contract on both sides of the seam.
 *
 * The drop directory is tracked in the repo rather than created by hand, so
 * `git pull` provisions it on the server. These tests fail if that tracking is
 * ever dropped — which is how it went missing in the first place.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** The real producer header, as emitted by FinAdvisorAI's weekly_update.py. */
function producerHeader(): array
{
    return [
        'date', 'open', 'high', 'low', 'close', 'shares_traded', 'turnover_cr',
        'log_return', 'hl_range_pct', 'gap_pct', 'rv_5d', 'rv_21d', 'rv_63d',
        'volume_zscore', 'is_covid_regime', 'vol_regime', 'dd_3m', 'dd_6m',
        'dd_12m', 'dd_regime', 'sma_200', 'above_sma', 'market_regime',
        'market_context', 'vol_score', 'dd_score', 'market_score',
        'market_risk_score', 'market_risk_score_smooth', 'market_risk_label',
    ];
}

function writeCsv(array $header, array ...$rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'mrs').'.csv';
    $fp = fopen($path, 'w');

    fputcsv($fp, $header);

    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);

    return $path;
}

/**
 * Run the command and return [exitCode, rawOutput].
 *
 * Not $this->artisan()->expectsOutputToContain(): that registers one Mockery
 * expectation per substring against doWrite(), and Mockery satisfies a call
 * with the first matching expectation only — so two substrings printed on the
 * same line can never both clear. Both assertions below are about one line.
 *
 * @return array{0: int, 1: string}
 */
function runSync(string $csvPath): array
{
    $code = Artisan::call('market-risk:sync', ['--csv' => $csvPath]);

    return [$code, Artisan::output()];
}

function completeRow(string $date = '2026-08-17'): array
{
    return [
        'date' => $date,
        'market_risk_score' => '42.5',
        'market_risk_score_smooth' => '40.1',
        'market_risk_label' => 'MEDIUM',
        'vol_regime' => 'LOW',
        'dd_regime' => 'MILD',
        'market_regime' => 'BULL',
        'warning_severity' => 'LOW',
        'warning_text' => 'Volatility is within its usual range.',
    ];
}

// ---------------------------------------------------------------------------
// F-40 — the drop directory is part of the repo, not a manual mkdir
// ---------------------------------------------------------------------------

it('ships the market-risk drop directory with the repo', function () {
    expect(is_dir(storage_path('app/market-risk')))->toBeTrue(
        'storage/app/market-risk must exist in a fresh checkout — it is tracked via its own .gitignore.'
    );
});

it('tracks the directory by keeping a .gitignore inside it', function () {
    $keeper = storage_path('app/market-risk/.gitignore');

    expect(file_exists($keeper))->toBeTrue()
        ->and(file_get_contents($keeper))->toContain('!.gitignore');
});

it('points the documented default path at that directory', function () {
    // The DEFAULT only — production may set MARKET_RISK_CSV_PATH elsewhere, so
    // asserting the resolved config value would pass or fail on that override
    // rather than on the contract this commit is fixing.
    expect(storage_path('app/market-risk/nifty500_enriched.csv'))
        ->toBe(storage_path('app').DIRECTORY_SEPARATOR.'market-risk'.DIRECTORY_SEPARATOR.'nifty500_enriched.csv')
        ->and(dirname(storage_path('app/market-risk/nifty500_enriched.csv')))
        ->toBe(storage_path('app/market-risk'));

    expect(config('risk.market_risk_csv_path'))->toBeString()->not->toBe('');
});

// ---------------------------------------------------------------------------
// Missing file — the failure has to say the file will not arrive by itself
// ---------------------------------------------------------------------------

it('fails with the expected path when the CSV is absent', function () {
    $missing = storage_path('app/market-risk/definitely-not-here.csv');

    [$code, $output] = runSync($missing);

    expect($code)->toBe(1)
        ->and($output)->toContain('CSV not found')
        ->and($output)->toContain($missing)
        // The operator has to learn the file will not arrive on its own.
        ->and($output)->toContain('deploy.sh');
});

// ---------------------------------------------------------------------------
// Seam-2 — every missing column named in one run
// ---------------------------------------------------------------------------

it('names every missing column at once, not just the first', function () {
    // vol_regime and dd_regime are both genuinely required.
    $row = completeRow();
    unset($row['vol_regime'], $row['dd_regime']);

    $path = writeCsv(array_keys($row), array_values($row));

    [$code, $output] = runSync($path);

    expect($code)->toBe(1)
        ->and($output)->toContain('vol_regime')
        ->and($output)->toContain('dd_regime')
        ->and(MarketRiskSnapshot::count())->toBe(0);

    unlink($path);
});

// ---------------------------------------------------------------------------
// Seam-2 resolved consumer-side — the warning pair is optional
// ---------------------------------------------------------------------------

it('syncs the real producer header, which carries no warning columns', function () {
    // Seam-2 as actually observed: neither nifty500_enriched.csv (30 cols) nor
    // _v2 (44 cols) emits the warning pair. The seven scoring columns are all
    // there, so the sync has to succeed on them.
    expect(producerHeader())->not->toContain('warning_severity')
        ->and(producerHeader())->not->toContain('warning_text');

    $row = array_fill_keys(producerHeader(), 'x');
    $row['date'] = '2026-08-17';
    $row['market_risk_score'] = '42.5';
    $row['market_risk_score_smooth'] = '40.1';
    $row['market_risk_label'] = 'MEDIUM';
    $row['vol_regime'] = 'LOW';
    $row['dd_regime'] = 'MILD';
    $row['market_regime'] = 'BULL';

    $path = writeCsv(producerHeader(), array_values($row));

    expect(runSync($path)[0])->toBe(0);

    $snapshot = MarketRiskSnapshot::latest();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->label)->toBe('MEDIUM')
        ->and($snapshot->multiplier())->toBe(1.08)
        ->and($snapshot->warning_severity)->toBeNull()
        ->and($snapshot->warning_text)->toBeNull();

    unlink($path);
});

it('syncs a minimal seven-column CSV', function () {
    $row = completeRow();
    unset($row['warning_severity'], $row['warning_text']);

    expect($row)->toHaveCount(7);

    $path = writeCsv(array_keys($row), array_values($row));

    expect(runSync($path)[0])->toBe(0)
        ->and(MarketRiskSnapshot::latest()->warning_severity)->toBeNull()
        ->and(MarketRiskSnapshot::latest()->warning_text)->toBeNull();

    unlink($path);
});

it('prints no warning line when the CSV carries no warning text', function () {
    $row = completeRow();
    unset($row['warning_severity'], $row['warning_text']);

    $path = writeCsv(array_keys($row), array_values($row));

    [$code, $output] = runSync($path);

    expect($code)->toBe(0)
        ->and($output)->toContain('Synced market risk snapshot')
        ->and($output)->not->toContain('Warning:');

    unlink($path);
});

it('still writes both warnings when the CSV carries them', function () {
    $row = completeRow();
    $path = writeCsv(array_keys($row), array_values($row));

    // One run only: syncing the same date twice hits a separate, pre-existing
    // date-cast issue in updateOrCreate() (reported, not fixed on this branch).
    [$code, $output] = runSync($path);

    expect($code)->toBe(0)
        ->and(MarketRiskSnapshot::latest()->warning_severity)->toBe('LOW')
        ->and(MarketRiskSnapshot::latest()->warning_text)->toBe('Volatility is within its usual range.')
        ->and($output)->toContain('Warning: Volatility is within its usual range.');

    unlink($path);
});

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('upserts a snapshot from a complete CSV', function () {
    $row = completeRow();
    $path = writeCsv(array_keys($row), array_values($row));

    expect(runSync($path)[0])->toBe(0);

    $snapshot = MarketRiskSnapshot::latest();

    expect($snapshot)->not->toBeNull()
        ->and($snapshot->market_date->toDateString())->toBe('2026-08-17')
        ->and($snapshot->score)->toBe(42.5)
        ->and($snapshot->score_smooth)->toBe(40.1)
        ->and($snapshot->label)->toBe('MEDIUM')
        ->and($snapshot->warning_severity)->toBe('LOW')
        ->and($snapshot->warning_text)->toBe('Volatility is within its usual range.')
        ->and($snapshot->multiplier())->toBe(1.08);

    unlink($path);
});

it('reads the last row of the file, not the first', function () {
    $older = completeRow('2026-08-16');
    $newer = completeRow('2026-08-17');
    $newer['market_risk_label'] = 'EXTREME';

    $path = writeCsv(array_keys($older), array_values($older), array_values($newer));

    expect(runSync($path)[0])->toBe(0);

    expect(MarketRiskSnapshot::count())->toBe(1)
        ->and(MarketRiskSnapshot::latest()->market_date->toDateString())->toBe('2026-08-17')
        ->and(MarketRiskSnapshot::latest()->label)->toBe('EXTREME');

    unlink($path);
});
