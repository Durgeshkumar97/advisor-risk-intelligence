<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * StockRiskService
 *
 * HTTP client for the risk_service microservice (POST /stock-risk),
 * which classifies NSE equity holdings as Low / Medium / High risk
 * from historical volatility.
 *
 * Guarantees to callers:
 *   - Never throws. The calling job (ProcessPortfolioFile) wraps DB writes
 *     and PDF generation in one transaction — a slow or down risk_service
 *     must never hang or fail portfolio processing.
 *   - `services.risk_service.timeout` is a TOTAL wall-clock budget, not a
 *     per-attempt one. Http::timeout() only bounds a single attempt, so
 *     naively combining it with ->retry() lets worst-case time balloon to
 *     attempts × timeout + sleep — well past the configured value. Instead
 *     the per-attempt timeout is derived from the budget (see requestPlan())
 *     so attempts × perAttemptTimeout + sleep never exceeds it.
 *   - Always returns one entry per requested symbol. When no usable
 *     classification exists (service down, timeout, error response,
 *     per-symbol upstream failure), the entry has risk_level=null and
 *     unavailable=true. This service only reports unavailability —
 *     the caller decides what to fall back to.
 *   - One batched HTTP call per invocation. Successful classifications are
 *     cached per symbol + date; fresh results for a few hours, stale ones
 *     (risk_service itself served cached/stale data) for a few minutes only
 *     — so once risk_service recovers, callers stop inheriting staleness
 *     almost immediately instead of for the full TTL. Failures are never
 *     cached, so recovery is picked up on the next call.
 */
class StockRiskService
{
    private const RISK_LEVELS = ['Low', 'Medium', 'High'];

    private const CACHE_TTL_HOURS = 4;

    private const CACHE_TTL_STALE_MINUTES = 10;

    /** Attempts when the budget comfortably fits a retry: 1 initial + 1 retry. */
    private const RETRY_ATTEMPTS = 2;

    private const RETRY_DELAY_MS = 200;

    /** Floor per attempt so a retry is never given an unusably tiny slice of the budget. */
    private const MIN_ATTEMPT_TIMEOUT = 0.5;

    /**
     * Defense-in-depth ceiling, independent of ProcessPortfolioFile's own cap —
     * protects this service's contract (and the downstream HTTP payload/compute
     * cost) for any caller, not just the one that currently exists. Should never
     * trip in normal operation; if it does, some caller isn't enforcing its own
     * limit before reaching here. Matches ProcessPortfolioFile::MAX_DISTINCT_STOCK_SYMBOLS.
     */
    private const MAX_BATCH_SYMBOLS = 500;

    protected string $url;

    protected float $timeout;

    public function __construct()
    {
        $this->url = rtrim((string) config('services.risk_service.url'), '/');
        $this->timeout = (float) config('services.risk_service.timeout', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | CLASSIFY BATCH
    |--------------------------------------------------------------------------
    */

    /**
     * Classify a batch of NSE stock symbols.
     *
     * @param  array<int, string>  $symbols  NSE trading symbols, e.g. ['RELIANCE', 'TCS']
     * @return array<string, array{
     *     risk_level: ?string,
     *     volatility: ?float,
     *     confidence: ?float,
     *     as_of_date: ?string,
     *     stale: bool,
     *     unavailable: bool
     * }> Keyed by the trimmed symbol as passed.
     */
    public function classifyBatch(array $symbols): array
    {
        $requested = collect($symbols)
            ->map(fn ($symbol) => trim((string) $symbol))
            ->filter()
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            return [];
        }

        if ($requested->count() > self::MAX_BATCH_SYMBOLS) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? null;

            Log::error('StockRiskService: classifyBatch() called with a batch exceeding the hard cap — the caller should have enforced its own limit before reaching here.', [
                'requested_count' => $requested->count(),
                'max_allowed' => self::MAX_BATCH_SYMBOLS,
                'caller' => $this->formatCallerFrame($caller),
            ]);

            return $requested->mapWithKeys(fn ($symbol) => [$symbol => $this->unavailable()])->all();
        }

        $results = [];
        $misses = [];

        foreach ($requested as $symbol) {
            $cached = Cache::get($this->cacheKey($symbol));

            if (is_array($cached)) {
                $results[$symbol] = $cached;
            } else {
                $misses[] = $symbol;
            }
        }

        if ($misses !== []) {
            $fetched = $this->fetchBatch(
                collect($misses)->map(fn ($s) => $this->normalize($s))->unique()->values()->all()
            );

            foreach ($misses as $symbol) {
                $result = $fetched[$this->normalize($symbol)] ?? $this->unavailable();

                if (! $result['unavailable']) {
                    // Stale results get a short TTL: risk_service already told us this
                    // data is old, so we shouldn't lock in that staleness locally for
                    // as long as a fresh result — recheck soon in case it recovers.
                    $ttl = $result['stale']
                        ? now()->addMinutes(self::CACHE_TTL_STALE_MINUTES)
                        : now()->addHours(self::CACHE_TTL_HOURS);

                    Cache::put($this->cacheKey($symbol), $result, $ttl);
                }

                $results[$symbol] = $result;
            }
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE
    |--------------------------------------------------------------------------
    */

    /**
     * One batched POST /stock-risk call.
     *
     * @param  array<int, string>  $symbols  Normalized (trimmed, uppercased) symbols
     * @return array<string, array> Parsed results keyed by normalized symbol;
     *                              empty when the service can't be used at all.
     */
    protected function fetchBatch(array $symbols): array
    {
        if ($this->url === '') {
            Log::warning('StockRiskService: RISK_SERVICE_URL is not configured.', [
                'symbols' => $symbols,
            ]);

            return [];
        }

        [$attempts, $perAttemptTimeout, $retryDelayMs] = $this->requestPlan();

        try {
            $response = Http::timeout($perAttemptTimeout)
                ->retry($attempts, $retryDelayMs, fn ($exception) => $exception instanceof ConnectionException, throw: false)
                ->post($this->url.'/stock-risk', [
                    'holdings' => array_map(fn ($symbol) => ['symbol' => $symbol], $symbols),
                ]);

            if (! $response->successful()) {
                Log::error('StockRiskService: risk_service returned an error response.', [
                    'status' => $response->status(),
                    'symbols' => $symbols,
                ]);

                return [];
            }

            return collect($response->json('results') ?? [])
                ->filter(fn ($result) => is_array($result) && ! empty($result['symbol']))
                ->keyBy(fn ($result) => $this->normalize($result['symbol']))
                ->map(fn ($result) => $this->parse($result))
                ->all();

        } catch (\Throwable $e) {
            Log::error('StockRiskService: risk_service unreachable.', [
                'message' => $e->getMessage(),
                'symbols' => $symbols,
            ]);

            return [];
        }
    }

    /**
     * Map one raw result row from the API onto the typed result shape.
     */
    protected function parse(array $raw): array
    {
        $riskLevel = $raw['risk_level'] ?? null;

        // A 200 batch response can still carry per-symbol failures:
        // schemas.py declares risk_level Optional, with `error` set instead.
        // Anything outside the Low/Medium/High contract is unavailable —
        // never coerced into a number.
        if (! in_array($riskLevel, self::RISK_LEVELS, true)) {
            if (! empty($raw['error'])) {
                Log::warning('StockRiskService: symbol classification failed upstream.', [
                    'symbol' => $raw['symbol'] ?? null,
                    'error' => $raw['error'],
                ]);
            }

            return $this->unavailable();
        }

        return [
            'risk_level' => $riskLevel,
            'volatility' => isset($raw['volatility']) ? (float) $raw['volatility'] : null,
            'confidence' => isset($raw['confidence']) ? (float) $raw['confidence'] : null,
            'as_of_date' => $raw['as_of_date'] ?? null,
            'stale' => (bool) ($raw['stale'] ?? false),
            'unavailable' => false,
        ];
    }

    protected function unavailable(): array
    {
        return [
            'risk_level' => null,
            'volatility' => null,
            'confidence' => null,
            'as_of_date' => null,
            'stale' => false,
            'unavailable' => true,
        ];
    }

    /**
     * Derive [attempts, perAttemptTimeoutSeconds, retryDelayMs] from the
     * configured total timeout budget, so that in the worst case
     * (every attempt uses its full timeout):
     *
     *   attempts × perAttemptTimeout + (attempts - 1) × retryDelaySeconds <= $this->timeout
     *
     * Http::timeout() only bounds a single attempt — chaining it with
     * ->retry() otherwise multiplies worst-case latency by the attempt
     * count instead of respecting one shared budget.
     *
     * @return array{0: int, 1: float, 2: int}
     */
    protected function requestPlan(): array
    {
        $retryDelaySeconds = self::RETRY_DELAY_MS / 1000;
        $budgetForTwoAttempts = 2 * self::MIN_ATTEMPT_TIMEOUT + $retryDelaySeconds;

        if ($this->timeout < $budgetForTwoAttempts) {
            // Budget too small to retry meaningfully — spend it all on one attempt
            // rather than starving two attempts below a usable floor.
            return [1, max(0.1, $this->timeout), 0];
        }

        $perAttemptTimeout = ($this->timeout - $retryDelaySeconds) / self::RETRY_ATTEMPTS;

        return [self::RETRY_ATTEMPTS, $perAttemptTimeout, self::RETRY_DELAY_MS];
    }

    /**
     * Formats a debug_backtrace() frame as "Class::method()" for the
     * batch-size-cap log line — best-effort context for tracking down
     * which caller isn't enforcing its own limit.
     */
    private function formatCallerFrame(?array $frame): string
    {
        if ($frame === null) {
            return 'unknown';
        }

        $function = $frame['function'] ?? 'unknown';
        $class = $frame['class'] ?? null;

        return $class ? "{$class}::{$function}()" : "{$function}()";
    }

    protected function normalize(string $symbol): string
    {
        return strtoupper(trim($symbol));
    }

    protected function cacheKey(string $symbol): string
    {
        return 'stock_risk:'.$this->normalize($symbol).':'.now()->toDateString();
    }
}
