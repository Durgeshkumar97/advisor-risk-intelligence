<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\DTOs;

use App\Services\IpoRisk\Enums\IpoState;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The raw extracted facts a scorer reads, before anything has been judged.
 *
 * Every fact is nullable, because a filing that does not disclose something
 * is the normal case and has to stay distinguishable from a zero. `state` is
 * the one exception: it is not a fact extracted from a document, it is the
 * question being asked, and everything else derives from it.
 */
final readonly class IpoInputs
{
    /**
     * @param  array<string, mixed>  $facts  extracted fact key => value (null means not disclosed)
     * @param  array<string, int>  $coveragePenalties  penalty key => occurrences
     */
    public function __construct(
        public IpoState $state,
        public ?string $companyName = null,
        public ?string $symbol = null,
        public array $facts = [],
        public array $coveragePenalties = [],
        public ?SentimentPanel $sentiment = null,
        public ?DateTimeImmutable $asOf = null,
    ) {
        foreach ($coveragePenalties as $key => $occurrences) {
            if (! is_string($key) || ! is_int($occurrences) || $occurrences < 0) {
                throw new InvalidArgumentException('Coverage penalties must be keyed by name with a non-negative integer count.');
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FACT ACCESS
    |--------------------------------------------------------------------------
    */

    public function fact(string $key, mixed $default = null): mixed
    {
        return $this->facts[$key] ?? $default;
    }

    /**
     * True only when the fact was actually extracted. A key present with a
     * null value means "looked for, not disclosed" — still absent.
     */
    public function has(string $key): bool
    {
        return ($this->facts[$key] ?? null) !== null;
    }

    public function sentiment(): SentimentPanel
    {
        return $this->sentiment ?? SentimentPanel::empty();
    }

    public function withFacts(array $facts): self
    {
        return new self(
            state: $this->state,
            companyName: $this->companyName,
            symbol: $this->symbol,
            facts: array_merge($this->facts, $facts),
            coveragePenalties: $this->coveragePenalties,
            sentiment: $this->sentiment,
            asOf: $this->asOf,
        );
    }
}
