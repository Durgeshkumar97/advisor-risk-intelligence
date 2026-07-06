<?php

use App\Services\StockRiskService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(\Tests\TestCase::class);

beforeEach(function () {
    config([
        'services.risk_service.url'     => 'http://risk-service.test',
        'services.risk_service.timeout' => 2,
    ]);

    Cache::flush();

    $this->service = new StockRiskService();
});

// ---------------------------------------------------------------------------
// Successful batch response
// ---------------------------------------------------------------------------

it('returns parsed risk classifications for a successful batch response', function () {
    Http::fake([
        'risk-service.test/stock-risk' => Http::response([
            'results' => [
                ['symbol' => 'RELIANCE', 'risk_level' => 'Low', 'volatility' => 18.4, 'confidence' => 0.91, 'as_of_date' => '2026-07-01', 'stale' => false],
                ['symbol' => 'ZOMATO', 'risk_level' => 'High', 'volatility' => 52.1, 'confidence' => 0.87, 'as_of_date' => '2026-07-01', 'stale' => true],
            ],
            'disclaimer' => 'Not investment advice.',
        ], 200),
    ]);

    $results = $this->service->classifyBatch(['RELIANCE', 'ZOMATO']);

    expect($results['RELIANCE'])->toBe([
        'risk_level'  => 'Low',
        'volatility'  => 18.4,
        'confidence'  => 0.91,
        'as_of_date'  => '2026-07-01',
        'stale'       => false,
        'unavailable' => false,
    ]);

    expect($results['ZOMATO'])->toBe([
        'risk_level'  => 'High',
        'volatility'  => 52.1,
        'confidence'  => 0.87,
        'as_of_date'  => '2026-07-01',
        'stale'       => true,
        'unavailable' => false,
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://risk-service.test/stock-risk'
            && $request['holdings'] === [['symbol' => 'RELIANCE'], ['symbol' => 'ZOMATO']];
    });
});

it('returns an empty array for an empty symbol list without making an HTTP call', function () {
    $results = $this->service->classifyBatch([]);

    expect($results)->toBe([]);
    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Failure handling — must never throw, must surface "unavailable" honestly
// ---------------------------------------------------------------------------

it('returns an unavailable result without throwing when risk_service times out', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    $results = null;

    expect(function () use (&$results) {
        $results = $this->service->classifyBatch(['RELIANCE']);
    })->not->toThrow(\Throwable::class);

    expect($results['RELIANCE'])->toBe([
        'risk_level'  => null,
        'volatility'  => null,
        'confidence'  => null,
        'as_of_date'  => null,
        'stale'       => false,
        'unavailable' => true,
    ]);
});

it('returns an unavailable result for every requested symbol on a non-2xx response', function () {
    Http::fake([
        'risk-service.test/stock-risk' => Http::response(['detail' => 'internal error'], 500),
    ]);

    $results = $this->service->classifyBatch(['RELIANCE', 'TCS']);

    expect($results['RELIANCE']['unavailable'])->toBeTrue()
        ->and($results['RELIANCE']['risk_level'])->toBeNull()
        ->and($results['TCS']['unavailable'])->toBeTrue()
        ->and($results['TCS']['risk_level'])->toBeNull();
});

it('marks a per-symbol upstream failure as unavailable instead of coercing a score', function () {
    // schemas.py: risk_level is Optional — a 200 batch response can still carry
    // a null risk_level with an `error` for an individual symbol (e.g. delisted,
    // insufficient history). This must never be silently treated as a valid score.
    Http::fake([
        'risk-service.test/stock-risk' => Http::response([
            'results' => [
                ['symbol' => 'NEWLISTING', 'risk_level' => null, 'error' => 'insufficient price history', 'stale' => false],
            ],
            'disclaimer' => 'Not investment advice.',
        ], 200),
    ]);

    $results = $this->service->classifyBatch(['NEWLISTING']);

    expect($results['NEWLISTING']['unavailable'])->toBeTrue()
        ->and($results['NEWLISTING']['risk_level'])->toBeNull();
});

// ---------------------------------------------------------------------------
// Local caching — avoid duplicate HTTP calls within one process/TTL window
// ---------------------------------------------------------------------------

it('does not repeat an HTTP call for a symbol already cached within the TTL window', function () {
    Http::fake([
        'risk-service.test/stock-risk' => Http::response([
            'results' => [
                ['symbol' => 'RELIANCE', 'risk_level' => 'Low', 'volatility' => 18.4, 'confidence' => 0.91, 'as_of_date' => '2026-07-01', 'stale' => false],
            ],
            'disclaimer' => 'Not investment advice.',
        ], 200),
    ]);

    $first  = $this->service->classifyBatch(['RELIANCE']);
    $second = $this->service->classifyBatch(['reliance']); // same symbol, different case

    expect($first['RELIANCE']['risk_level'])->toBe('Low');
    expect($second['reliance']['risk_level'])->toBe('Low');
    Http::assertSentCount(1);
});

it('does not cache a failed lookup, so the next call retries the HTTP request', function () {
    Http::fake([
        'risk-service.test/stock-risk' => Http::sequence()
            ->push(['detail' => 'internal error'], 500)
            ->push([
                'results' => [
                    ['symbol' => 'RELIANCE', 'risk_level' => 'Low', 'volatility' => 18.4, 'confidence' => 0.91, 'as_of_date' => '2026-07-01', 'stale' => false],
                ],
                'disclaimer' => 'Not investment advice.',
            ], 200),
    ]);

    $first  = $this->service->classifyBatch(['RELIANCE']);
    $second = $this->service->classifyBatch(['RELIANCE']);

    expect($first['RELIANCE']['unavailable'])->toBeTrue();
    expect($second['RELIANCE']['risk_level'])->toBe('Low');
    Http::assertSentCount(2);
});

it('expires a stale-flagged result from the local cache sooner than a fresh one', function () {
    Http::fake([
        'risk-service.test/stock-risk' => Http::sequence()
            ->push([
                'results' => [
                    ['symbol' => 'RELIANCE', 'risk_level' => 'Low', 'volatility' => 18.4, 'confidence' => 0.91, 'as_of_date' => '2026-06-30', 'stale' => true],
                    ['symbol' => 'TCS', 'risk_level' => 'High', 'volatility' => 40.0, 'confidence' => 0.88, 'as_of_date' => '2026-07-01', 'stale' => false],
                ],
                'disclaimer' => 'Not investment advice.',
            ], 200)
            ->push([
                'results' => [
                    ['symbol' => 'RELIANCE', 'risk_level' => 'Medium', 'volatility' => 28.0, 'confidence' => 0.85, 'as_of_date' => '2026-07-01', 'stale' => false],
                ],
                'disclaimer' => 'Not investment advice.',
            ], 200),
    ]);

    $this->service->classifyBatch(['RELIANCE', 'TCS']);
    Http::assertSentCount(1);

    // RELIANCE was cached stale=true (10-minute TTL). TCS was cached stale=false
    // (4-hour TTL). Travelling 11 minutes expires only the stale entry.
    $this->travel(11)->minutes();

    $results = $this->service->classifyBatch(['RELIANCE', 'TCS']);

    Http::assertSentCount(2);

    // The refetch request must only re-request RELIANCE — TCS is still a cache hit.
    Http::assertSent(fn ($request) => $request['holdings'] === [['symbol' => 'RELIANCE']]);

    expect($results['RELIANCE']['risk_level'])->toBe('Medium') // refreshed after expiry
        ->and($results['TCS']['risk_level'])->toBe('High');    // untouched, still cached
});

// ---------------------------------------------------------------------------
// Defense-in-depth batch-size cap — independent of ProcessPortfolioFile's own
// cap. Called directly here, bypassing the job entirely, to prove this layer
// works on its own rather than only in tandem with the caller-side check.
// ---------------------------------------------------------------------------

it('rejects a batch over the hard cap without making an HTTP call', function () {
    Http::fake();

    $symbols = collect(range(1, 501))->map(fn ($i) => "SYM{$i}")->all();

    $results = $this->service->classifyBatch($symbols);

    Http::assertNothingSent();

    expect($results)->toHaveCount(501);

    foreach ($results as $result) {
        expect($result['unavailable'])->toBeTrue()
            ->and($result['risk_level'])->toBeNull();
    }
});

it('still processes a batch at exactly the cap normally', function () {
    Http::fake([
        'risk-service.test/stock-risk' => Http::response([
            'results' => collect(range(1, 500))->map(fn ($i) => [
                'symbol' => "SYM{$i}", 'risk_level' => 'Low', 'volatility' => 15.0,
                'confidence' => 0.9, 'as_of_date' => '2026-07-01', 'stale' => false,
            ])->all(),
            'disclaimer' => 'Not investment advice.',
        ], 200),
    ]);

    $symbols = collect(range(1, 500))->map(fn ($i) => "SYM{$i}")->all();

    $results = $this->service->classifyBatch($symbols);

    Http::assertSentCount(1);
    expect($results)->toHaveCount(500)
        ->and($results['SYM1']['unavailable'])->toBeFalse()
        ->and($results['SYM1']['risk_level'])->toBe('Low');
});

// ---------------------------------------------------------------------------
// Bounded total timeout — Http::timeout() only bounds a single attempt, so
// naively chaining ->retry() on top of it lets worst-case latency multiply
// past the configured budget. requestPlan() derives a per-attempt timeout
// from the total budget instead. Http::fake() responds instantly regardless
// of the timeout option passed to Guzzle, so real wall-clock time can't be
// observed through it — this proves the bound mathematically instead, against
// the same worst-case formula the retry loop actually executes.
// ---------------------------------------------------------------------------

it('derives a per-attempt timeout so worst-case total time never exceeds the configured budget', function () {
    foreach ([0.5, 1.0, 2.0, 3.0, 5.0, 10.0] as $timeout) {
        config(['services.risk_service.timeout' => $timeout]);
        $service = new StockRiskService();

        $plan = new ReflectionMethod($service, 'requestPlan');
        [$attempts, $perAttemptTimeout, $retryDelayMs] = $plan->invoke($service);

        $worstCaseTotal = $attempts * $perAttemptTimeout + ($attempts - 1) * ($retryDelayMs / 1000);

        expect($worstCaseTotal)->toBeLessThanOrEqual($timeout)
            ->and($attempts)->toBeGreaterThanOrEqual(1)
            ->and($perAttemptTimeout)->toBeGreaterThan(0.0);
    }
});

it('retries once within a 2-second budget without exceeding it', function () {
    config(['services.risk_service.timeout' => 2.0]);
    $service = new StockRiskService();

    [$attempts, $perAttemptTimeout, $retryDelayMs] = (new ReflectionMethod($service, 'requestPlan'))->invoke($service);

    expect($attempts)->toBe(2)
        ->and($perAttemptTimeout)->toBe(0.9)
        ->and($retryDelayMs)->toBe(200);
});

it('collapses to a single attempt when the budget is too small to retry meaningfully', function () {
    config(['services.risk_service.timeout' => 1.0]);
    $service = new StockRiskService();

    [$attempts, $perAttemptTimeout, $retryDelayMs] = (new ReflectionMethod($service, 'requestPlan'))->invoke($service);

    expect($attempts)->toBe(1)
        ->and($perAttemptTimeout)->toBe(1.0)
        ->and($retryDelayMs)->toBe(0);
});
