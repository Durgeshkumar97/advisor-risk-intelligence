<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\DTOs;

use DateTimeImmutable;

/**
 * Grey-market premium, displayed and never scored.
 *
 * GMP is an unregulated, unverifiable, off-exchange quote. It is shown
 * because advisors will look it up anyway and are better served seeing it
 * labelled than seeing it absent — but it carries no weight in config, no
 * FactorFamily case, and no path into the score. IpoRiskEngine refuses to
 * register a scorer whose code starts with GMP.
 *
 * SCORED is a constant rather than a property so that "this is never scored"
 * is a fact about the type, not a value someone can pass in. (It is untyped
 * because the project runs PHP 8.2; typed class constants are 8.3.)
 */
final readonly class SentimentPanel
{
    public const SCORED = false;

    public function __construct(
        public ?float $gmpValue = null,
        public ?float $gmpPercent = null,
        public ?string $gmpSource = null,
        public ?DateTimeImmutable $gmpObservedAt = null,
        public ?Provenance $provenance = null,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | FACTORIES
    |--------------------------------------------------------------------------
    */

    public static function empty(): self
    {
        return new self;
    }

    public function isObserved(): bool
    {
        return $this->gmpValue !== null || $this->gmpPercent !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scored' => self::SCORED,
            'gmp_value' => $this->gmpValue,
            'gmp_percent' => $this->gmpPercent,
            'gmp_source' => $this->gmpSource,
            'gmp_observed_at' => $this->gmpObservedAt?->format(DATE_ATOM),
            'provenance' => $this->provenance?->toArray(),
        ];
    }
}
