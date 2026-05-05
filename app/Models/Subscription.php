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
        'provider_subscription_id'
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
    | HELPERS (CORE SAAS LOGIC)
    |--------------------------------------------------------------------------
    */

    public function isActive()
    {
        return $this->status === 'active'
            && $this->ends_at
            && $this->ends_at->isFuture();
    }

    public function isTrial()
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function isExpired()
    {
        return $this->ends_at && $this->ends_at->isPast();
    }
}