<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\FxRateProvider;
use App\DTOs\FxQuote;
use Illuminate\Support\Carbon;

/** An exchange-rate source for tests: one fixed rate, or none at all. */
final class FixedFxRate implements FxRateProvider
{
    public const RATE = 95.985;

    public const SOURCE = 'Test rate — invented';

    public function __construct(
        private readonly ?float $rate = self::RATE,
        private readonly string $asOf = '2026-09-29',
    ) {}

    public static function none(): self
    {
        return new self(null);
    }

    public function current(string $currency): ?FxQuote
    {
        return $this->rate === null || $currency !== 'USD'
            ? null
            : new FxQuote('USD', $this->rate, Carbon::parse($this->asOf), self::SOURCE);
    }
}
