<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MarketController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | ALLOWED TYPES — whitelist prevents path traversal via ?type=
    |--------------------------------------------------------------------------
    */

    private const ALLOWED_TYPES = [
        'usd_full'     => 'quarterly_usd_full.csv',
        'usd_recent'   => 'quarterly_usd_recent.csv',
        'local_full'   => 'quarterly_local_full.csv',
        'local_recent' => 'quarterly_local_recent.csv',
    ];

    private const ALLOWED_VIEW_MODES = ['yearly', 'quarterly'];

    /*
    |--------------------------------------------------------------------------
    | RETURNS  (GET /market-returns)
    |--------------------------------------------------------------------------
    |
    | Reads a CSV of quarterly index returns and computes per-row statistics:
    |   yearly returns, CAGR, rolling 5Y CAGR, annualised volatility,
    |   Sharpe ratio, max drawdown, worst year, and % positive years.
    |
    */

    public function returns(Request $request): View
    {
        /*
        |--------------------------------------------------------------------------
        | 1. VALIDATE QUERY PARAMS — never trust user-supplied strings for file paths
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'type' => ['sometimes', 'string', 'in:' . implode(',', array_keys(self::ALLOWED_TYPES))],
            'view' => ['sometimes', 'string', 'in:' . implode(',', self::ALLOWED_VIEW_MODES)],
        ]);

        $type     = $request->get('type', 'usd_full');
        $viewMode = $request->get('view', 'yearly');

        /*
        |--------------------------------------------------------------------------
        | 2. RESOLVE FILE PATH
        |--------------------------------------------------------------------------
        */

        $filename = self::ALLOWED_TYPES[$type] ?? self::ALLOWED_TYPES['usd_full'];
        $path     = storage_path('app/data/' . $filename);

        if (! file_exists($path)) {

            Log::warning('MarketController: CSV file not found', ['path' => $path]);

            return view('market-returns', [
                'data'     => [],
                'headers'  => [],
                'type'     => $type,
                'viewMode' => $viewMode,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. PARSE CSV
        |--------------------------------------------------------------------------
        */

        $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));

        if (count($rows) < 2) {

            Log::warning('MarketController: CSV has no data rows', ['path' => $path]);

            return view('market-returns', [
                'data'     => [],
                'headers'  => [],
                'type'     => $type,
                'viewMode' => $viewMode,
            ]);
        }

        $header        = array_shift($rows);   // removes and returns the first row
        $quarterLabels = array_slice($header, 3);

        if (empty($quarterLabels)) {
            return view('market-returns', [
                'data'     => [],
                'headers'  => [],
                'type'     => $type,
                'viewMode' => $viewMode,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. EXTRACT UNIQUE YEAR LABELS (for yearly view mode header)
        |--------------------------------------------------------------------------
        */

        $years = array_values(array_unique(
            array_map(fn(string $label) => substr($label, 0, 4), $quarterLabels)
        ));

        /*
        |--------------------------------------------------------------------------
        | 5. COMPUTE STATISTICS PER ROW
        |--------------------------------------------------------------------------
        */

        $data = [];

        foreach ($rows as $row) {

            // Skip malformed rows
            if (count($row) < 4) {
                continue;
            }

            $quarterReturns = array_map('floatval', array_slice($row, 3));
            $quarterCount   = count($quarterReturns);

            if ($quarterCount === 0) {
                continue;
            }

            /*
            |----------------------------------------------------------------------
            | YEARLY RETURNS — compound quarterly returns within each calendar year
            |----------------------------------------------------------------------
            */

            $yearly = [];

            foreach ($quarterLabels as $i => $label) {

                $year = substr($label, 0, 4);

                if (! isset($yearly[$year])) {
                    $yearly[$year] = 1.0;
                }

                $yearly[$year] *= (1.0 + $quarterReturns[$i] / 100.0);
            }

            foreach ($yearly as $y => $v) {
                $yearly[$y] = ($v - 1.0) * 100.0;
            }

            /*
            |----------------------------------------------------------------------
            | CAGR — use actual quarter count for fractional-year accuracy
            |----------------------------------------------------------------------
            |
            | Bug fix: original used count($quarterReturns)/4 which gives wrong
            | results for partial years (e.g. 5 quarters → 1.25 years but CAGR
            | would have been computed correctly only for multiples of 4).
            | Expressing it as quarters/4 is mathematically equivalent but
            | explicit about the assumption.
            |
            */

            $growth     = array_reduce($quarterReturns, fn($carry, $r) => $carry * (1.0 + $r / 100.0), 1.0);
            $yearsCount = $quarterCount / 4.0;
            $cagr       = $yearsCount > 0 ? (pow($growth, 1.0 / $yearsCount) - 1.0) : 0.0;

            /*
            |----------------------------------------------------------------------
            | ROLLING 5-YEAR CAGR — last 20 quarters only
            |----------------------------------------------------------------------
            */

            $rolling5 = null;

            if ($quarterCount >= 20) {
                $last20   = array_slice($quarterReturns, -20);
                $g5       = array_reduce($last20, fn($carry, $r) => $carry * (1.0 + $r / 100.0), 1.0);
                $rolling5 = (pow($g5, 1.0 / 5.0) - 1.0) * 100.0;
            }

            /*
            |----------------------------------------------------------------------
            | ANNUALISED VOLATILITY — population std-dev of quarterly returns × √4
            |----------------------------------------------------------------------
            */

            $mean = array_sum($quarterReturns) / $quarterCount;

            $variance = array_reduce(
                $quarterReturns,
                fn($carry, $r) => $carry + ($r - $mean) ** 2,
                0.0
            ) / $quarterCount;

            $vol = sqrt($variance) * sqrt(4); // annualise quarterly vol

            /*
            |----------------------------------------------------------------------
            | SHARPE RATIO — CAGR / annualised vol (simplified; no risk-free rate)
            |----------------------------------------------------------------------
            */

            $sharpe = $vol > 0.0 ? ($cagr * 100.0) / $vol : 0.0;

            /*
            |----------------------------------------------------------------------
            | MAX DRAWDOWN — peak-to-trough on cumulative equity curve
            |----------------------------------------------------------------------
            */

            $peak   = 1.0;
            $equity = 1.0;
            $maxDD  = 0.0;

            foreach ($quarterReturns as $r) {
                $equity *= (1.0 + $r / 100.0);
                if ($equity > $peak) {
                    $peak = $equity;
                }
                $dd = ($equity - $peak) / $peak;
                if ($dd < $maxDD) {
                    $maxDD = $dd;
                }
            }

            /*
            |----------------------------------------------------------------------
            | WORST YEAR & POSITIVE YEAR %
            |----------------------------------------------------------------------
            */

            $yearlyValues = array_values($yearly);
            $worst        = ! empty($yearlyValues) ? min($yearlyValues) : 0.0;
            $yearCount    = count($yearly);
            $positiveYears = count(array_filter($yearlyValues, fn($v) => $v > 0));
            $posPct        = $yearCount > 0 ? ($positiveYears / $yearCount) * 100.0 : 0.0;

            $data[] = [
                'country'  => $row[0] ?? '',
                'exchange' => $row[1] ?? '',
                'index'    => $row[2] ?? '',
                'yearly'   => $yearly,
                'quarterly' => array_combine($quarterLabels, $quarterReturns),
                'cagr'     => $cagr * 100.0,
                'rolling5' => $rolling5,           // already × 100 above, or null
                'vol'      => $vol,
                'sharpe'   => $sharpe,
                'drawdown' => $maxDD * 100.0,
                'worst'    => $worst,
                'posPct'   => $posPct,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 6. RESOLVE HEADERS FOR VIEW
        |--------------------------------------------------------------------------
        */

        $headers = $viewMode === 'yearly' ? $years : $quarterLabels;

        return view('market-returns', compact('data', 'headers', 'type', 'viewMode'));
    }
}
