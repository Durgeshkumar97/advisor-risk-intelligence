<?php

namespace App\Enums;

/**
 * Canonical payment statuses used across the payments table.
 *
 * The Payment model still carries the matching string constants
 * (Payment::STATUS_*) for backwards compatibility — this enum is
 * the single source of truth going forward.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Paid, self::Failed, self::Refunded => true,
            default => false,
        };
    }
}
