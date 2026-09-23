<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Compliance;

/**
 * Blocks prescriptive and promotional language in user-facing narrative.
 *
 * The operator is not a SEBI-registered Investment Adviser. Everything this
 * module publishes states a condition — "receivables form 98% of total
 * assets" — and never prescribes an action. The difference is not stylistic:
 * one is an observation about a filing, the other is investment advice the
 * operator is not registered to give.
 *
 * The term list is the union of this module's own vocabulary and the house
 * rule already documented in PortfolioRiskCalculator::buildNextAction(); see
 * config/ipo_risk.php for both origins. There is no allowlist, by decision: a
 * false positive gets the sentence rewritten, not exempted, because an
 * allowlist is a list of phrases someone once argued were fine.
 *
 * Scope: user-facing narrative strings only. Never run it over code, config
 * keys or enum values — enum case names are covered separately, by a test
 * that asserts none of them contains a prohibited term.
 *
 * Matching is case-insensitive and word-boundaried, so "Safeguards" is not
 * "safe" and "selling shareholders" is not "sell". Multi-word terms match as
 * phrases across any run of whitespace, so a line break inside "good
 * investment" does not evade it.
 */
final class ObservationalLanguageFilter
{
    /** @var array<int, string> */
    private array $terms;

    /**
     * @param  array<int, string>|null  $terms  defaults to the configured list
     */
    public function __construct(?array $terms = null)
    {
        $normalised = array_map(
            static fn ($term): string => trim((string) $term),
            $terms ?? (array) config('ipo_risk.prohibited_terms', []),
        );

        $this->terms = array_values(array_unique(array_filter(
            $normalised,
            static fn (string $term): bool => $term !== '',
        )));
    }

    /**
     * @return array<int, string>
     */
    public function terms(): array
    {
        return $this->terms;
    }

    /**
     * Every prohibited term present, in configured order. Empty when clean.
     *
     * @return array<int, string>
     */
    public function violations(string $text): array
    {
        $found = [];

        foreach ($this->terms as $term) {
            if (preg_match($this->pattern($term), $text) === 1) {
                $found[] = $term;
            }
        }

        return $found;
    }

    public function isClean(string $text): bool
    {
        return $this->violations($text) === [];
    }

    /**
     * @throws ProhibitedLanguageException listing every term matched
     */
    public function assertClean(string $text, ?string $context = null): void
    {
        $violations = $this->violations($text);

        if ($violations !== []) {
            throw ProhibitedLanguageException::forTerms($violations, $text, $context);
        }
    }

    /**
     * No /u modifier: the terms are ASCII, and \b is byte-based, so the
     * pattern behaves identically while staying safe against a stray
     * non-UTF-8 byte in an OCR'd source string (which would make a /u match
     * return false and silently pass the text).
     */
    private function pattern(string $term): string
    {
        return '/\b'.str_replace(' ', '\s+', preg_quote($term, '/')).'\b/i';
    }
}
