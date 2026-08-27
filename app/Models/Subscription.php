<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'lead_id',
        'user_id',
        'plan_id',
        'status',
        'trial_started_at',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'renewal_at',
        'provider',
        'provider_subscription_id',
    ];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'renewal_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function lead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The payment that created or renewed this subscription.
     *
     * provider_subscription_id is populated with the Razorpay PAYMENT id
     * (SubscriptionService::activate() and CreateSubscriptionFromPaymentAction
     * both write $payment->payment_id into it), so it must be matched against
     * payments.payment_id. It previously compared against payments.order_id,
     * which holds a different Razorpay identifier entirely — so the relation
     * never resolved and always came back null.
     */
    public function payment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payment::class, 'payment_id', 'provider_subscription_id');
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->ends_at
            && $this->ends_at->isFuture();
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        // Treat an elapsed trial as expired immediately, without waiting for the cron.
        if ($this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isPast()) {
            return true;
        }

        return $this->ends_at && $this->ends_at->isPast();
    }

    public function isInGracePeriod(): bool
    {
        if (! $this->isExpired()) {
            return false;
        }

        return $this->ends_at
            && now()->lessThan($this->ends_at->addDays(3));
    }

    public function daysRemaining(): int
    {
        $end = $this->ends_at ?? $this->trial_ends_at;

        if (! $end || $end->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($end, false);
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->isActive() && $this->daysRemaining() <= $days;
    }
}
