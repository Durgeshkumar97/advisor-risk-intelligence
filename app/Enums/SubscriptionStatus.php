<?php

namespace App\Enums;

/**
 * Canonical subscription statuses used across the subscriptions table.
 *
 * Usage in Subscription model helpers:
 *   isActive()  → status === SubscriptionStatus::Active
 *   isTrial()   → status === SubscriptionStatus::Trial
 *   isExpired() → ends_at in the past
 */
enum SubscriptionStatus: string
{
    case Trial   = 'trial';
    case Active  = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function label(): string
    {
        return match ($this) {
            self::Trial     => 'Free Trial',
            self::Active    => 'Active',
            self::Expired   => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isAccessible(): bool
    {
        return match ($this) {
            self::Trial, self::Active => true,
            default                   => false,
        };
    }
}
