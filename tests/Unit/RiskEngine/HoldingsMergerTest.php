<?php

use App\Services\RiskEngine\HoldingsMerger;

/**
 * HoldingsMerger works on normalised holdings only — these tests build that
 * shape by hand, with no file, parser or broker involved.
 */
function holding(array $overrides = []): array
{
    return array_merge([
        'name' => 'Example Flexi Cap Fund',
        'asset_type' => 'mutual_fund',
        'symbol' => null,
        'isin' => 'INF000TEST01',
        'quantity' => 100.0,
        'buy_price' => 100.0,
        'current_price' => 120.0,
        'invested_value' => 10000.0,
        'current_value' => 12000.0,
        'profit_loss' => 2000.0,
        'invested_value_source' => 'file',
        'source_file' => 'broker-a.xlsx',
        'currency' => 'INR',
        'cost_known' => true,
        'value_basis' => 'market',
        'as_of' => null,
    ], $overrides);
}

it('sums quantity, value and cost for the same ISIN held through two brokers', function () {
    $merged = (new HoldingsMerger)->merge([
        holding(),
        holding([
            'name' => 'EXAMPLE FLEXI CAP FUND - DIRECT',   // brokers name the same fund differently
            'source_file' => 'broker-b.csv',
            'quantity' => 40.0, 'invested_value' => 4400.0, 'current_value' => 4800.0, 'profit_loss' => 400.0,
        ]),
    ]);

    expect($merged)->toHaveCount(1)
        ->and($merged[0]['quantity'])->toBe(140.0)
        ->and($merged[0]['invested_value'])->toBe(14400.0)
        ->and($merged[0]['current_value'])->toBe(16800.0)
        ->and($merged[0]['profit_loss'])->toBe(2400.0)
        ->and($merged[0]['current_price'])->toBe(120.0)
        ->and(array_column($merged[0]['sources'], 'source_file'))->toBe(['broker-a.xlsx', 'broker-b.csv']);
});

it('matches on a normalised name when a holding has no ISIN', function () {
    $merged = (new HoldingsMerger)->merge([
        holding(['isin' => null, 'name' => 'Example  Industries Ltd']),
        holding(['isin' => null, 'name' => ' example industries ltd ', 'source_file' => 'broker-b.csv']),
    ]);

    expect($merged)->toHaveCount(1)
        ->and($merged[0]['quantity'])->toBe(200.0);
});

it('keeps holdings apart when both have ISINs and the ISINs differ, even with the same name', function () {
    $merged = (new HoldingsMerger)->merge([
        holding(['isin' => 'INF000TEST01']),
        holding(['isin' => 'INF000TEST02', 'source_file' => 'broker-b.csv']),
    ]);

    expect($merged)->toHaveCount(2);
});

it('never merges two rows from the same source file', function () {
    $merged = (new HoldingsMerger)->merge([
        holding(),
        holding(),   // same fund in two folios of ONE export
    ]);

    expect($merged)->toHaveCount(2)
        ->and($merged[0]['quantity'])->toBe(100.0);
});

it('makes the merged cost unknown when any contributing source has no cost', function () {
    $merged = (new HoldingsMerger)->merge([
        holding(),                                           // cost 10,000, value 12,000
        holding([
            'source_file' => 'depository.csv',
            'invested_value' => null, 'profit_loss' => null,
            'invested_value_source' => 'unknown', 'cost_known' => false,
            'quantity' => 50.0, 'current_value' => 6000.0,
        ]),
    ]);

    // A partial sum (10,000 against a value of 18,000) would read as +80% profit.
    expect($merged)->toHaveCount(1)
        ->and($merged[0]['current_value'])->toBe(18000.0)
        ->and($merged[0]['invested_value'])->toBeNull()
        ->and($merged[0]['profit_loss'])->toBeNull()
        ->and($merged[0]['cost_known'])->toBeFalse()
        ->and($merged[0]['invested_value_source'])->toBe('unknown')
        ->and(array_column($merged[0]['sources'], 'cost_known'))->toBe([true, false]);
});

it('carries provenance through a merge: the oldest date wins and each source is recorded', function () {
    $merged = (new HoldingsMerger)->merge([
        holding(['as_of' => '2026-09-29']),
        holding(['source_file' => 'quarterly-statement.pdf', 'as_of' => '2026-06-30', 'cost_known' => false, 'invested_value' => null, 'profit_loss' => null, 'invested_value_source' => 'unknown']),
        holding(['source_file' => 'undated.csv', 'as_of' => null]),
    ]);

    expect($merged)->toHaveCount(1)
        ->and($merged[0]['as_of'])->toBe('2026-06-30')        // a stale source cannot hide behind a fresh one
        ->and($merged[0]['currency'])->toBe('INR')
        ->and($merged[0]['value_basis'])->toBe('market')
        ->and($merged[0]['cost_known'])->toBeFalse()
        ->and($merged[0]['sources'])->toBe([
            ['source_file' => 'broker-a.xlsx', 'cost_known' => true, 'as_of' => '2026-09-29'],
            ['source_file' => 'quarterly-statement.pdf', 'cost_known' => false, 'as_of' => '2026-06-30'],
            ['source_file' => 'undated.csv', 'cost_known' => true, 'as_of' => null],
        ]);
});

it('never merges holdings in different currencies or with different value bases', function () {
    $merged = (new HoldingsMerger)->merge([
        holding(),
        holding(['source_file' => 'us-broker.xls', 'currency' => 'USD']),
        holding(['source_file' => 'cost-only.xls', 'value_basis' => 'cost']),
    ]);

    expect($merged)->toHaveCount(3);
});

it('passes a single source through unchanged apart from recording its source', function () {
    $input = holding();
    $merged = (new HoldingsMerger)->merge([$input]);

    expect($merged[0]['sources'])->toBe([['source_file' => 'broker-a.xlsx', 'cost_known' => true, 'as_of' => null]])
        ->and(array_diff_key($merged[0], ['sources' => 1]))->toBe(array_diff_key($input, ['source_file' => 1]));
});
