<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use Illuminate\Support\Collection;

uses(\Tests\TestCase::class);

/**
 * Renders reports.risk-report directly with unsaved model instances — the
 * view only needs attribute access, not persistence, and the confidence/
 * stale indicators are meta-driven so no DB round trip is needed to verify
 * them.
 */
function renderRiskReport(array $assets, array $riskScoreOverrides = []): string
{
    $riskScore = new RiskScore(array_merge([
        'score' => 45.0,
        'volatility' => 18.5,
        'drawdown' => 12.0,
        'meta' => [],
    ], $riskScoreOverrides));

    $file = new PortfolioFile(['original_name' => 'holdings.csv']);

    return view('reports.risk-report', [
        'portfolio' => null,
        'riskScore' => $riskScore,
        'assets' => new Collection($assets),
        'file' => $file,
    ])->render();
}

function stockAsset(?float $confidence, bool $stale = false): PortfolioAsset
{
    return new PortfolioAsset([
        'name' => 'Reliance Industries',
        'asset_type' => 'stock',
        'symbol' => 'RELIANCE',
        'quantity' => 10,
        'current_value' => 25000,
        'profit_loss' => 1200,
        'risk_score' => 40,
        'risk_level' => 'MEDIUM',
        'meta' => [
            'stock_risk' => [
                'confidence' => $confidence,
                'stale' => $stale,
            ],
        ],
    ]);
}

it('shows no confidence indicator for a normally-confident holding', function () {
    $html = renderRiskReport([stockAsset(0.85)]);

    expect($html)->not->toContain('Low confidence');
    expect($html)->not->toContain('scored with limited');
});

it('flags a low-confidence holding without hiding or altering its score', function () {
    $html = renderRiskReport([stockAsset(0.55)]);

    expect($html)->toContain('Low confidence');
    expect($html)->toContain('scored with limited');
    // The score itself is still printed, unchanged — transparency, not a gate.
    expect($html)->toContain('40');
    expect($html)->toContain('MEDIUM');
});

it('does not flag a holding exactly at the 0.70 confidence boundary', function () {
    $html = renderRiskReport([stockAsset(0.70)]);

    expect($html)->not->toContain('Low confidence');
});

it('flags a stale holding separately from a low-confidence one', function () {
    $html = renderRiskReport([stockAsset(0.9, stale: true)]);

    expect($html)->toContain('Data may be outdated');
    expect($html)->not->toContain('Low confidence');
});

it('does not error on a non-stock asset with no stock_risk meta', function () {
    $asset = new PortfolioAsset([
        'name' => 'Gold ETF',
        'asset_type' => 'commodity',
        'quantity' => 5,
        'current_value' => 8000,
        'profit_loss' => -200,
        'risk_score' => 20,
        'risk_level' => 'LOW',
        'meta' => [],
    ]);

    $html = renderRiskReport([$asset]);

    expect($html)->not->toContain('Low confidence');
    expect($html)->not->toContain('Data may be outdated');
});
