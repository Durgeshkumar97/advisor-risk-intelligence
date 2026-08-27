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

    /** Captured by the gateway, but fulfilment failed and needs manual completion. */
    case RequiresReview = 'requires_review';

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
            self::RequiresReview => 'Requires Review',
        };
    }

    public function isTerminal(): bool
    {
        // RequiresReview is terminal for the queue — the job has exhausted its
        // retries and will not run again on its own. It is not terminal for the
        // business: a human still has to complete fulfilment.
        return match ($this) {
            self::Paid, self::Failed, self::Refunded, self::RequiresReview => true,
            default => false,
        };
    }
}
