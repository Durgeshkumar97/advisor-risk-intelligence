<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Compliance;

use RuntimeException;

/**
 * Thrown when a user-facing string contains prescriptive or promotional
 * language.
 *
 * Deliberately an exception rather than a sanitiser. Silently stripping "you
 * should sell" leaves a sentence that no longer says what its author meant,
 * and ships it anyway. Failing loudly forces the string to be rewritten by
 * someone who knows what it was for.
 */
final class ProhibitedLanguageException extends RuntimeException
{
    /**
     * @param  array<int, string>  $terms  every term matched, not just the first
     */
    public function __construct(
        public readonly array $terms,
        public readonly string $subject = '',
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : self::describe($terms));
    }

    /**
     * @param  array<int, string>  $terms
     */
    public static function forTerms(array $terms, string $subject, ?string $context = null): self
    {
        $where = $context === null ? '' : " in {$context}";

        return new self(
            terms: $terms,
            subject: $subject,
            message: 'Prohibited language'.$where.': '.self::describe($terms),
        );
    }

    /**
     * @param  array<int, string>  $terms
     */
    private static function describe(array $terms): string
    {
        return implode(', ', array_map(
            static fn (string $term): string => '"'.$term.'"',
            $terms,
        ));
    }
}
