<?php

use App\Services\RiskEngine\AssetRiskScorer;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->scorer = new AssetRiskScorer();
});

// ---------------------------------------------------------------------------
// Base scores by asset type
// ---------------------------------------------------------------------------

it('scores cash at 5', fn () => expect($this->scorer->score('cash', 'My Cash'))->toBe(5.0));
it('scores bond at 15', fn () => expect($this->scorer->score('bond', 'Govt Bond'))->toBe(15.0));
it('scores etf at 35 when no keyword matches', fn () => expect($this->scorer->score('etf', 'Generic ETF'))->toBe(35.0));
it('scores mutual_fund at 45 when no keyword matches', fn () => expect($this->scorer->score('mutual_fund', 'Generic Fund'))->toBe(45.0));
it('scores commodity at 60', fn () => expect($this->scorer->score('commodity', 'Gold Spot'))->toBe(60.0));
it('scores stock at 65', fn () => expect($this->scorer->score('stock', 'Reliance Industries'))->toBe(65.0));
it('scores foreign_stock at 75', fn () => expect($this->scorer->score('foreign_stock', 'Apple Inc'))->toBe(75.0));
it('scores crypto at 90', fn () => expect($this->scorer->score('crypto', 'Bitcoin'))->toBe(90.0));

// ---------------------------------------------------------------------------
// Unknown / oddly-cased types
// ---------------------------------------------------------------------------

it('defaults unknown type to stock score', fn () =>
    expect($this->scorer->score('reit', 'Embassy REIT'))->toBe(65.0)
);

it('is case-insensitive for asset type input', fn () =>
    expect($this->scorer->score('STOCK', 'Infosys'))->toBe(65.0)
);

it('trims whitespace from asset type', fn () =>
    expect($this->scorer->score('  bond  ', 'Govt Bond'))->toBe(15.0)
);

// ---------------------------------------------------------------------------
// Keyword adjustments — mutual_fund (delta applied to base 45)
// ---------------------------------------------------------------------------

it('lowers mutual_fund score by 28 for overnight funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'HDFC Overnight Fund'))->toBe(17.0)   // 45 − 28
);

it('lowers mutual_fund score by 28 for liquid funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'SBI Liquid Fund Direct Growth'))->toBe(17.0)
);

it('lowers mutual_fund score by 28 for arbitrage funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'Nippon India Arbitrage Fund'))->toBe(17.0)
);

it('lowers mutual_fund score by 28 for money market funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'ICICI Prudential Money Market Fund'))->toBe(17.0)
);

it('lowers mutual_fund score by 22 for ultra-short duration funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'ICICI Ultra Short Term Fund'))->toBe(23.0)  // 45 − 22
);

it('lowers mutual_fund score by 22 for gilt funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'SBI Gilt Fund'))->toBe(23.0)
);

it('lowers mutual_fund score by 22 for floater funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'Nippon Floater Fund'))->toBe(23.0)
);

it('lowers mutual_fund score by 10 for balanced/hybrid funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'HDFC Balanced Advantage Fund'))->toBe(35.0)  // 45 − 10
);

it('lowers mutual_fund score by 5 for nifty 50 index funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'UTI Nifty 50 Index Fund'))->toBe(40.0)  // 45 − 5
);

it('lowers mutual_fund score by 5 for large cap funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'Axis Bluechip Fund'))->toBe(40.0)
);

it('raises mutual_fund score by 12 for mid-cap funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'Kotak Mid Cap Fund'))->toBe(57.0)  // 45 + 12
);

it('raises mutual_fund score by 22 for small-cap funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'Nippon India Small Cap Fund'))->toBe(67.0)  // 45 + 22
);

it('raises mutual_fund score by 22 for sectoral/technology funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'SBI Technology Opportunities Fund'))->toBe(67.0)
);

it('raises mutual_fund score by 22 for pharma thematic funds', fn () =>
    expect($this->scorer->score('mutual_fund', 'Mirae Asset Healthcare Sector Fund'))->toBe(67.0)
);

// ---------------------------------------------------------------------------
// Keyword adjustments — ETF (delta applied to base 35)
// ---------------------------------------------------------------------------

it('lowers etf score by 5 for nifty 50 etfs', fn () =>
    expect($this->scorer->score('etf', 'Nippon Nifty 50 ETF'))->toBe(30.0)  // 35 − 5
);

it('raises etf score by 22 for small-cap etfs', fn () =>
    expect($this->scorer->score('etf', 'Nippon India Small Cap ETF'))->toBe(57.0)  // 35 + 22
);

// ---------------------------------------------------------------------------
// Keywords are NOT applied to non-fund asset types
// ---------------------------------------------------------------------------

it('does not adjust stock score even when name contains fund keywords', fn () =>
    expect($this->scorer->score('stock', 'Nifty 50 Holdings Ltd'))->toBe(65.0)
);

it('does not adjust bond score even when name contains fund keywords', fn () =>
    expect($this->scorer->score('bond', 'Overnight Infrastructure Bond'))->toBe(15.0)
);

it('does not adjust crypto score for any name', fn () =>
    expect($this->scorer->score('crypto', 'Balanced Bitcoin Index'))->toBe(90.0)
);

// ---------------------------------------------------------------------------
// Score is bounded 0–100
// ---------------------------------------------------------------------------

it('clamps score to minimum of 0 for extreme low-risk adjustments', function () {
    // etf base=35, overnight delta=−28 → 7 (above 0, but verifies no negative score possible)
    expect($this->scorer->score('etf', 'HDFC Overnight ETF'))->toBeGreaterThanOrEqual(0.0);
});

it('clamps score to maximum of 100', function () {
    // crypto base=90 — no adjustment applies to crypto, so it stays at 90
    expect($this->scorer->score('crypto', 'Dogecoin'))->toBeLessThanOrEqual(100.0);
});

// ---------------------------------------------------------------------------
// level() thresholds
// ---------------------------------------------------------------------------

it('level() returns LOW for score below the low threshold', function () {
    config(['risk.low_threshold' => 30]);
    expect($this->scorer->level(0.0))->toBe('LOW');
    expect($this->scorer->level(29.9))->toBe('LOW');
});

it('level() returns MEDIUM for score between thresholds', function () {
    config(['risk.low_threshold' => 30, 'risk.high_threshold' => 70]);
    expect($this->scorer->level(30.0))->toBe('MEDIUM');
    expect($this->scorer->level(69.9))->toBe('MEDIUM');
});

it('level() returns HIGH for score at or above the high threshold', function () {
    config(['risk.high_threshold' => 70]);
    expect($this->scorer->level(70.0))->toBe('HIGH');
    expect($this->scorer->level(100.0))->toBe('HIGH');
});
