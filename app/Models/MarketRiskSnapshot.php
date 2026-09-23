<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class MarketRiskSnapshot extends Model
{
    /** market_date is an NSE trading date, so ages are measured in IST. */
    public const MARKET_TIMEZONE = 'Asia/Kolkata';

    protected $fillable = [
        'market_date',
        'score',
        'score_smooth',
        'label',
        'vol_regime',
        'dd_regime',
        'market_regime',
        'warning_severity',
        'warning_text',
    ];

    protected $casts = [
        'market_date'  => 'date',
        'score'        => 'float',
        'score_smooth' => 'float',
    ];

    /**
     * The newest snapshot, however old that is.
     *
     * Deliberately NOT age-filtered. The report has to be able to say "last
     * data 2026-08-17", and a query that hides the row destroys the date that
     * statement depends on. Age is a caveat the caller applies — the same
     * shape StockRiskService already uses for its `stale` flag.
     */
    public static function latest(): ?self
    {
        return static::orderByDesc('market_date')->first();
    }

    /**
     * Whole calendar days between this snapshot's trading date and today, IST.
     *
     * market_date is an NSE trading date, which is an IST calendar date and not
     * an instant. So compare dates, never datetimes: the column stores
     * 00:00:00, and comparing timestamps would let a server clock in another
     * zone — or a future change to config('app.timezone') — move the boundary
     * by a day. Negative if the snapshot is somehow dated in the future.
     */
    public function ageInDays(): int
    {
        $marketDate = CarbonImmutable::createFromFormat(
            'Y-m-d',
            $this->market_date->toDateString(),
            self::MARKET_TIMEZONE,
        )->startOfDay();

        $today = CarbonImmutable::now(self::MARKET_TIMEZONE)->startOfDay();

        // Both are IST midnights and IST observes no DST, so the difference is
        // an exact multiple of 86400 — no rounding drift to worry about.
        return (int) round(($today->getTimestamp() - $marketDate->getTimestamp()) / 86400);
    }

    /**
     * Older than the configured limit, and therefore not to be applied.
     *
     * Exactly at the limit is still fresh.
     */
    public function isStale(): bool
    {
        return $this->ageInDays() > (int) config('risk.market_snapshot_max_age_days', 7);
    }

    public function multiplier(): float
    {
        return match($this->label) {
            'LOW'     => 1.00,
            'MEDIUM'  => 1.08,
            'HIGH'    => 1.15,
            'EXTREME' => 1.30,
            default   => 1.05,
        };
    }
}
