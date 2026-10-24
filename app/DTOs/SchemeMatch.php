<?php

namespace App\DTOs;

use App\Models\MfScheme;
use App\Services\Amfi\SchemeMatcher;

/**
 * The AMFI scheme a holding was matched to, how, and how surely.
 *
 * A match is usable at or above SchemeMatcher::PROBABLE and probable below
 * SchemeMatcher::HIGH. method 'none' carries the best score that was found
 * (or 0) so a near miss is visible on the stored holding.
 */
final class SchemeMatch
{
    public const METHOD_ISIN = 'isin';

    public const METHOD_EXACT = 'exact';

    public const METHOD_FUZZY = 'fuzzy';

    public const METHOD_NONE = 'none';

    public function __construct(
        public readonly ?MfScheme $scheme,
        public readonly string $method,
        public readonly float $confidence,
    ) {}

    public static function none(float $bestScore = 0.0): self
    {
        return new self(null, self::METHOD_NONE, $bestScore);
    }

    public function isUsable(): bool
    {
        return $this->scheme !== null && $this->confidence >= SchemeMatcher::PROBABLE;
    }

    public function isProbable(): bool
    {
        return $this->isUsable() && $this->confidence < SchemeMatcher::HIGH;
    }
}
