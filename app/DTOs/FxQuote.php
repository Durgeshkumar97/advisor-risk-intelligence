<?php

namespace App\DTOs;

use Carbon\CarbonInterface;

/**
 * An exchange rate as the rest of the application sees it: rupees per 1 unit
 * of the foreign currency, the date it is for, and where it came from.
 */
final class FxQuote
{
    /** Older than this, a rate is still used but the report says so. */
    public const STALE_AFTER_DAYS = 7;

    /** Older than this, a rate is not used at all. */
    public const EXPIRED_AFTER_DAYS = 31;

    public function __construct(
        public readonly string $currency,
        public readonly float $rate,
        public readonly CarbonInterface $asOf,
        public readonly string $source,
    ) {}

    public function ageInDays(?CarbonInterface $on = null): int
    {
        return (int) $this->asOf->copy()->startOfDay()->diffInDays(($on ?? now())->copy()->startOfDay(), false);
    }

    public function isStale(?CarbonInterface $on = null): bool
    {
        return $this->ageInDays($on) > self::STALE_AFTER_DAYS;
    }

    public function isExpired(?CarbonInterface $on = null): bool
    {
        return $this->ageInDays($on) > self::EXPIRED_AFTER_DAYS;
    }
}
