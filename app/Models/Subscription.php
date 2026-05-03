<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Plan;
use App\Models\Lead;

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
        'provider_subscription_id'
    ];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'trial_ends_at'   => 'datetime',
        'starts_at'       => 'datetime',
        'ends_at'         => 'datetime',
        'renewal_at'      => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /*
    |--------------------------------------------------------------------------
    | State Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->ends_at instanceof Carbon
            && $this->ends_at->isFuture();
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at instanceof Carbon
            && $this->trial_ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->ends_at instanceof Carbon
            && $this->ends_at->isPast();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (VERY IMPORTANT)
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('ends_at', '>', now());
    }

    public function scopeTrial($query)
    {
        return $query->where('status', 'trial')
            ->where('trial_ends_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('ends_at')
            ->where('ends_at', '<', now());
    }

    /*
    |--------------------------------------------------------------------------
    | Utility
    |--------------------------------------------------------------------------
    */

    public function daysRemaining(): int
    {
        if (!$this->ends_at) {
            return 0;
        }

        return now()->diffInDays($this->ends_at, false);
    }
}