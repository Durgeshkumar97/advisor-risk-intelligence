<?php

use App\Models\PortfolioFile;
use App\Models\RiskScore;
use Illuminate\Support\Collection;

uses(\Tests\TestCase::class);

/**
 * The Market Risk Context block in the client PDF, with warning_severity and
 * warning_text now optional.
 *
 * The colour is the point. A missing annotation must never be able to repaint a
 * risky market as calm, so green is reachable only from an explicit LOW and
 * everything unrecognised falls to neutral grey.
 */
const SEV_GREEN = '#16a34a';
const SEV_GREY = '#6b7280';
const SEV_GREY_BG = '#f3f4f6';
const SEV_RED = '#dc2626';
const SEV_PURPLE = '#8e44ad';
const SEV_AMBER = '#d97706';

/**
 * The colour actually applied to the market context box.
 *
 * Asserting on the bare hex would be meaningless: #16a34a, #dc2626 and #d97706
 * all appear in the template's stylesheet (.risk-low, .risk-high, .risk-medium)
 * on every render, so `not->toContain('#16a34a')` could never fail. Only the
 * box's own inline border carries the severity decision.
 */
function borderColour(string $html): ?string
{
    preg_match('/border-left: 4px solid (#[0-9a-f]{6})/i', $html, $m);

    return $m[1] ?? null;
}

function backgroundColour(string $html): ?string
{
    preg_match('/border-left: 4px solid #[0-9a-f]{6};\s*background: (#[0-9a-f]{6})/i', $html, $m);

    return $m[1] ?? null;
}

function renderMarketContext(array $context): string
{
    $riskScore = new RiskScore([
        'score' => 45.0,
        'volatility' => 18.5,
        'drawdown' => 12.0,
        'meta' => ['market_context' => $context],
    ]);

    return view('reports.risk-report', [
        'portfolio' => null,
        'riskScore' => $riskScore,
        'assets' => new Collection,
        'file' => new PortfolioFile(['original_name' => 'holdings.csv']),
    ])->render();
}

function marketContext(array $overrides = []): array
{
    return array_merge([
        'date' => '2026-08-17',
        'score' => 42.5,
        'score_smooth' => 40.1,
        'label' => 'MEDIUM',
        'warning_severity' => 'MEDIUM',
        'warning_text' => 'Volatility is within its usual range.',
        'vol_regime' => 'LOW',
        'dd_regime' => 'MILD',
        'market_regime' => 'BULL',
        'multiplier_used' => 1.08,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Null warnings — no empty box, no empty line
// ---------------------------------------------------------------------------

it('renders the market context block with both warnings null', function () {
    $html = renderMarketContext(marketContext([
        'warning_severity' => null,
        'warning_text' => null,
    ]));

    expect($html)->toContain('Market Risk Context')
        ->toContain('Market Environment')
        ->toContain('as of 2026-08-17')
        // The parts that do not depend on the warning pair still render.
        ->toContain('Volatility: LOW')
        ->toContain('Drawdown: MILD')
        ->toContain('Trend: BULL');
});

it('leaves no empty warning line when warning_text is null', function () {
    $withText = renderMarketContext(marketContext());
    $withoutText = renderMarketContext(marketContext([
        'warning_severity' => null,
        'warning_text' => null,
    ]));

    expect($withText)->toContain('Volatility is within its usual range.')
        ->and($withoutText)->not->toContain('Volatility is within its usual range.')
        // The <span> that would have held it is gone, not merely empty.
        ->and(substr_count($withoutText, '<span style="font-size:10px;">'))->toBe(0)
        ->and(substr_count($withText, '<span style="font-size:10px;">'))->toBe(1);
});

// ---------------------------------------------------------------------------
// Green is never a fallback
// ---------------------------------------------------------------------------

it('renders grey, never green, when severity and label are both null', function () {
    $html = renderMarketContext(marketContext([
        'warning_severity' => null,
        'label' => null,
    ]));

    expect(borderColour($html))->toBe(SEV_GREY)
        ->and(backgroundColour($html))->toBe(SEV_GREY_BG)
        ->and(borderColour($html))->not->toBe(SEV_GREEN);
});

it('renders grey, never green, for an unrecognised label', function () {
    $html = renderMarketContext(marketContext([
        'warning_severity' => null,
        'label' => 'UNKNOWN',
    ]));

    expect(borderColour($html))->toBe(SEV_GREY)->not->toBe(SEV_GREEN);
});

it('renders grey when severity is an empty string', function () {
    $html = renderMarketContext(marketContext([
        'warning_severity' => '',
        'label' => null,
    ]));

    expect(borderColour($html))->toBe(SEV_GREY)->not->toBe(SEV_GREEN);
});

it('requires an explicit LOW for green', function () {
    $html = renderMarketContext(marketContext([
        'warning_severity' => 'LOW',
        'warning_text' => 'Markets are quiet.',
    ]));

    expect(borderColour($html))->toBe(SEV_GREEN);
});

// ---------------------------------------------------------------------------
// Severity falls back to the label, so a risky market keeps its colour
// ---------------------------------------------------------------------------

it('keeps a risky market coloured when only the severity is missing', function (string $label, string $colour) {
    $html = renderMarketContext(marketContext([
        'warning_severity' => null,
        'warning_text' => null,
        'label' => $label,
    ]));

    expect(borderColour($html))->toBe($colour)
        ->not->toBe(SEV_GREEN)
        ->not->toBe(SEV_GREY);
})->with([
    'extreme' => ['EXTREME', SEV_PURPLE],
    'high' => ['HIGH', SEV_RED],
    'medium' => ['MEDIUM', SEV_AMBER],
]);

it('prefers an explicit severity over the label', function () {
    $html = renderMarketContext(marketContext([
        'warning_severity' => 'EXTREME',
        'label' => 'LOW',
    ]));

    expect(borderColour($html))->toBe(SEV_PURPLE)->not->toBe(SEV_GREEN);
});

// ---------------------------------------------------------------------------
// Stale snapshot — one dated sentence, and nothing else
// ---------------------------------------------------------------------------

it('replaces the coloured box with a dated unavailable line when stale', function () {
    $html = renderMarketContext(marketContext([
        'stale' => true,
        'age_days' => 35,
    ]));

    expect($html)->toContain('Market Risk Context')
        ->toContain('Market context unavailable — last data 2026-08-17');
});

it('states nothing else in the stale state', function () {
    $html = renderMarketContext(marketContext([
        'stale' => true,
        'age_days' => 35,
    ]));

    // None of the regime reads survive: they describe a market from five weeks
    // ago and no multiplier was applied on their basis.
    expect($html)->not->toContain('Market Environment')
        ->not->toContain('as of 2026-08-17')
        ->not->toContain('Volatility: LOW')
        ->not->toContain('Drawdown: MILD')
        ->not->toContain('Trend: BULL')
        ->not->toContain('Market Score:')
        ->not->toContain('Volatility is within its usual range.');
});

it('never colours a stale box by severity', function (string $severity) {
    $html = renderMarketContext(marketContext([
        'stale' => true,
        'warning_severity' => $severity,
        'label' => $severity,
    ]));

    expect(borderColour($html))->toBe(SEV_GREY)
        ->and(backgroundColour($html))->toBe(SEV_GREY_BG);
})->with(['LOW', 'MEDIUM', 'HIGH', 'EXTREME']);

it('treats an absent stale key as fresh, so old reports keep rendering', function () {
    $context = marketContext();
    expect($context)->not->toHaveKey('stale');

    $html = renderMarketContext($context);

    expect($html)->toContain('Market Environment')
        ->not->toContain('Market context unavailable');
});

it('treats stale false as fresh', function () {
    $html = renderMarketContext(marketContext(['stale' => false, 'age_days' => 2]));

    expect($html)->toContain('Market Environment')
        ->and(borderColour($html))->toBe(SEV_AMBER)
        ->and($html)->not->toContain('Market context unavailable');
});

// ---------------------------------------------------------------------------
// No snapshot at all — the block stays away entirely
// ---------------------------------------------------------------------------

it('omits the block when no market context was recorded', function () {
    $riskScore = new RiskScore([
        'score' => 45.0,
        'volatility' => 18.5,
        'drawdown' => 12.0,
        'meta' => [],
    ]);

    $html = view('reports.risk-report', [
        'portfolio' => null,
        'riskScore' => $riskScore,
        'assets' => new Collection,
        'file' => new PortfolioFile(['original_name' => 'holdings.csv']),
    ])->render();

    expect($html)->not->toContain('Market Risk Context');
});
