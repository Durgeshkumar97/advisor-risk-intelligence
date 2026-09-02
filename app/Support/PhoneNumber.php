<?php

namespace App\Support;

/**
 * Normalizes phone numbers to E.164 format (+<country_code><local_number>).
 * Supports any country via the dial code lookup in config/countries.php.
 */
class PhoneNumber
{
    /**
     * Normalizes a phone number from user input (with optional leading 0 or
     * country code) to E.164 format: +<dialcode><number>. Returns null if
     * invalid (wrong length, contains non-digits after stripping formatting).
     *
     * Handles cases where the number already contains the country code (e.g.
     * "+91 9876543210" or "91-9876543210"), or when it's a standalone number.
     *
     * @param string $dialCode    E.164 dial code like "+91" or "91" or "+1"
     * @param string|null $number Local number or full E.164 number (with/without formatting)
     * @return string|null         E.164 normalized number, or null if invalid
     */
    public static function normalize(?string $dialCode, ?string $number): ?string
    {
        if ($dialCode === null || $number === null) {
            return null;
        }

        $dialCode = trim($dialCode);
        $number = trim($number);

        if ($dialCode === '' || $number === '') {
            return null;
        }

        // Strip all non-digits from both inputs
        $dialCodeDigits = preg_replace('/\D+/', '', $dialCode);
        $numberDigits = preg_replace('/\D+/', '', $number);

        // Ensure we have a dial code
        if ($dialCodeDigits === '') {
            return null;
        }

        // If the number already starts with the dial code, don't double it
        if (str_starts_with($numberDigits, $dialCodeDigits)) {
            $numberDigits = substr($numberDigits, strlen($dialCodeDigits));
        }

        if ($numberDigits === '') {
            return null;
        }

        // ITU-T E.164 spec: max 15 digits total (excluding the + prefix)
        // Most countries: 1-3 digits (dial code) + 7-14 digits (local)
        if (strlen($numberDigits) < 7 || strlen($numberDigits) > 14) {
            return null;
        }

        return '+'.$dialCodeDigits.$numberDigits;
    }

    /**
     * Legacy: normalizes Indian-only phone numbers for backward compatibility.
     * Returns +91XXXXXXXXXX, or null if invalid.
     */
    public static function toIndianE164(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        } elseif (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (! preg_match('/^[6-9]\d{9}$/', $digits)) {
            return null;
        }

        return '+91'.$digits;
    }
}
