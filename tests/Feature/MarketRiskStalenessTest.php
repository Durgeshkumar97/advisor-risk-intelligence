<?php

use App\Jobs\ProcessPortfolioFile;
use App\Models\MarketRiskSnapshot;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * Snapshot staleness.
 *
 * market_date is an NSE trading date — an IST calendar date, not an instant —
 * so every boundary here is pinned with Carbon::setTestNow at a fixed IST
 * moment, including just after IST midnight, where a UTC server clock would
 * still be on the previous calendar day.
 */
afterEach(function () {
    Carbon::setTestNow();
});

function snapshot(string $marketDate = '2026-08-17', array $overrides = []): MarketRiskSnapshot
{
    return MarketRiskSnapshot::create(array_merge([
        'market_date' => $marketDate,
        'score' => 85.0,
        'score_smooth' => 84.0,
        'label' => 'HIGH',
        'vol_regime' => 'HIGH',
        'dd_regime' => 'SEVERE',
        'market_regime' => 'BEAR',
        'warning_severity' => null,
        'warning_text' => null,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Age is counted in whole IST calendar days
// ---------------------------------------------------------------------------

it('counts whole calendar days from the trading date', function (string $nowIst, int $expected) {
    Carbon::setTestNow(Carbon::parse($nowIst, 'Asia/Kolkata'));

    expect(snapshot('2026-08-17')->ageInDays())->toBe($expected);
})->with([
    'same day' => ['2026-08-17 15:45:00', 0],
    'next day' => ['2026-08-18 09:00:00', 1],
    'seven days' => ['2026-08-24 12:00:00', 7],
    'eight days' => ['2026-08-25 12:00:00', 8],
    'five weeks' => ['2026-09-21 12:00:00', 35],
]);

it('counts the same age just after IST midnight as later that day', function () {
    // 00:05 IST on the 25th is 18:35 UTC on the 24th. A UTC-based comparison
    // would call this 7 days and leave the snapshot fresh.
    Carbon::setTestNow(Carbon::parse('2026-08-25 00:05:00', 'Asia/Kolkata'));
    $row = snapshot('2026-08-17');
    $justAfterMidnight = $row->ageInDays();

    Carbon::setTestNow(Carbon::parse('2026-08-25 23:55:00', 'Asia/Kolkata'));
    $lateSameDay = $row->ageInDays();

    expect($justAfterMidnight)->toBe(8)->toBe($lateSameDay);
});

it('reports a negative age for a snapshot dated in the future', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00', 'Asia/Kolkata'));

    $row = snapshot('2026-08-19');

    expect($row->ageInDays())->toBe(-2)
        ->and($row->isStale())->toBeFalse();
});

// ---------------------------------------------------------------------------
// The boundary: exactly at the limit is fresh, one day past is stale
// ---------------------------------------------------------------------------

it('treats exactly seven days as fresh', function (string $nowIst) {
    Carbon::setTestNow(Carbon::parse($nowIst, 'Asia/Kolkata'));

    $snapshot = snapshot('2026-08-17');

    expect($snapshot->ageInDays())->toBe(7)
        ->and($snapshot->isStale())->toBeFalse();
})->with([
    'midday' => ['2026-08-24 12:00:00'],
    'just after IST midnight' => ['2026-08-24 00:05:00'],
    'just before IST midnight' => ['2026-08-24 23:55:00'],
]);

it('treats eight days as stale', function (string $nowIst) {
    Carbon::setTestNow(Carbon::parse($nowIst, 'Asia/Kolkata'));

    $snapshot = snapshot('2026-08-17');

    expect($snapshot->ageInDays())->toBe(8)
        ->and($snapshot->isStale())->toBeTrue();
})->with([
    'midday' => ['2026-08-25 12:00:00'],
    'just after IST midnight' => ['2026-08-25 00:05:00'],
]);

it('follows the configured limit', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 12:00:00', 'Asia/Kolkata'));

    $row = snapshot('2026-08-17');

    config()->set('risk.market_snapshot_max_age_days', 3);
    expect($row->isStale())->toBeTrue();

    config()->set('risk.market_snapshot_max_age_days', 30);
    expect($row->isStale())->toBeFalse();
});

it('does not hide a stale row from latest(), because the report needs its date', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00', 'Asia/Kolkata'));

    snapshot('2026-08-17');

    expect(MarketRiskSnapshot::latest())->not->toBeNull()
        ->and(MarketRiskSnapshot::latest()->isStale())->toBeTrue()
        ->and(MarketRiskSnapshot::latest()->market_date->toDateString())->toBe('2026-08-17');
});

// ---------------------------------------------------------------------------
// The job applies the policy
// ---------------------------------------------------------------------------

function runJob(): RiskScore
{
    Storage::fake('portfolios');
    Mail::fake();

    $csv = implode("\n", [
        'name,asset_type,symbol,current_value,buy_price,current_price,quantity',
        'Govt Bond,bond,,10000,100,100,100',
    ]);

    $user = User::factory()->create();
    $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Test Portfolio']);

    Storage::disk('portfolios')->put('uploads/portfolio.csv', $csv);

    $file = PortfolioFile::create([
        'user_id' => $user->id,
        'portfolio_id' => $portfolio->id,
        'original_name' => 'portfolio.csv',
        'stored_name' => 'portfolio.csv',
        'path' => 'uploads/portfolio.csv',
        'mime_type' => 'text/csv',
        'file_size' => strlen($csv),
        'status' => PortfolioFile::STATUS_PENDING,
    ]);

    ProcessPortfolioFile::dispatchSync($file);

    return RiskScore::where('user_id', $user->id)->latest('id')->firstOrFail();
}

it('applies a fresh snapshot multiplier', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 12:00:00', 'Asia/Kolkata'));
    config()->set('risk.market_multiplier', 1.00);

    snapshot('2026-08-17');   // HIGH -> 1.15

    $riskScore = runJob();
    $context = $riskScore->meta['market_context'];

    expect($context['stale'])->toBeFalse()
        ->and($context['age_days'])->toBe(7)
        ->and($context['multiplier_used'])->toBe(1.15)
        ->and($riskScore->meta['market_multiplier'])->toBe(1.15);
});

it('falls back to the config multiplier when the snapshot is stale', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00', 'Asia/Kolkata'));
    config()->set('risk.market_multiplier', 1.00);

    snapshot('2026-08-17');   // HIGH -> 1.15, but 35 days old

    $riskScore = runJob();
    $context = $riskScore->meta['market_context'];

    // Cast: meta is a JSON column and json_encode drops the zero fraction, so a
    // whole-number multiplier round-trips as int 1. Display-only, pre-existing.
    expect($context['stale'])->toBeTrue()
        ->and($context['age_days'])->toBe(35)
        // The config fallback, NOT the snapshot's own 1.15.
        ->and((float) $context['multiplier_used'])->toBe(1.0)
        ->and((float) $riskScore->meta['market_multiplier'])->toBe(1.0);
});

it('still records the snapshot date and label when stale', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00', 'Asia/Kolkata'));

    snapshot('2026-08-17');

    $context = runJob()->meta['market_context'];

    expect($context['date'])->toBe('2026-08-17')
        ->and($context['label'])->toBe('HIGH');
});

it('logs a warning naming the age and the limit when stale', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-21 12:00:00', 'Asia/Kolkata'));

    Log::spy();

    snapshot('2026-08-17');
    runJob();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'stale')
            && ($context['age_days'] ?? null) === 35
            && ($context['max_age_days'] ?? null) === 7)
        ->once();
});

it('does not warn about staleness for a fresh snapshot', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 12:00:00', 'Asia/Kolkata'));

    Log::spy();

    snapshot('2026-08-17');
    runJob();

    Log::shouldNotHaveReceived('warning', [
        \Mockery::on(fn ($message) => is_string($message) && str_contains($message, 'stale')),
        \Mockery::any(),
    ]);
});
