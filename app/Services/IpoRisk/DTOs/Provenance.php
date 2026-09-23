<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Where a fact came from.
 *
 * Attached to every non-null factor score and every fired gate, so that any
 * number this module publishes can be traced back to a page of a filing. An
 * observation without a source is not an observation, it is an opinion — and
 * the operator is not registered to offer one.
 */
final readonly class Provenance
{
    public function __construct(
        public string $sourceDocument,
        public ?int $sourcePage,
        public DateTimeImmutable $asOf,
    ) {
        if (trim($sourceDocument) === '') {
            throw new InvalidArgumentException('Provenance requires a non-empty source_document.');
        }

        if ($sourcePage !== null && $sourcePage < 1) {
            throw new InvalidArgumentException("Provenance source_page must be a positive page number, got {$sourcePage}.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORY
    |--------------------------------------------------------------------------
    */

    public static function of(
        string $sourceDocument,
        ?int $sourcePage = null,
        ?DateTimeImmutable $asOf = null,
    ): self {
        return new self(
            sourceDocument: $sourceDocument,
            sourcePage: $sourcePage,
            asOf: $asOf ?? new DateTimeImmutable,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_document' => $this->sourceDocument,
            'source_page' => $this->sourcePage,
            'as_of' => $this->asOf->format(DATE_ATOM),
        ];
    }
}
