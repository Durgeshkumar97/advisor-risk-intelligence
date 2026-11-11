<?php

namespace App\Support;

/**
 * Numbers as an Indian reader expects them: lakh/crore grouping
 * (₹1,14,000.00, not ₹114,000.00) and the sign before the rupee symbol
 * (-₹7,500.00, not ₹-7,500.00).
 *
 * Pure PHP on purpose: the intl extension's en_IN formatter would do the
 * grouping, but its presence on the production host is not guaranteed.
 */
final class IndianFormat
{
    /** Rupee amount with paise. $signed adds '+' to gains (losses always show '-'). */
    public static function money(float|int|string $amount, bool $signed = false): string
    {
        $rounded = round((float) $amount, 2);

        // -0.004 rounds to -0.0, which must not print as "-₹0.00".
        if ($rounded == 0.0) {
            return '₹0.00';
        }

        $sign = $rounded < 0 ? '-' : ($signed ? '+' : '');

        return $sign.'₹'.self::group(number_format(abs($rounded), 2, '.', ''));
    }

    /** Holding quantity: up to four decimals, no trailing zeros (400, 12.5). */
    public static function quantity(float|int|string $quantity): string
    {
        $text = number_format(round((float) $quantity, 4), 4, '.', '');
        $text = rtrim(rtrim($text, '0'), '.');

        if ($text === '-0' || $text === '') {
            $text = '0';
        }

        $negative = str_starts_with($text, '-');

        return ($negative ? '-' : '').self::group(ltrim($text, '-'));
    }

    /** "1234567.50" → "12,34,567.50": the last three integer digits, then pairs. */
    private static function group(string $plain): string
    {
        [$integer, $fraction] = array_pad(explode('.', $plain, 2), 2, null);

        if (strlen($integer) > 3) {
            // Comma before every pair of digits counted from the right.
            $rest = preg_replace('/\B(?=(\d{2})+$)/', ',', substr($integer, 0, -3));
            $integer = $rest.','.substr($integer, -3);
        }

        return $fraction === null ? $integer : $integer.'.'.$fraction;
    }
}
