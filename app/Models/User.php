<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_admin',
        'login_token',
        'login_method',
        'onboarding_completed',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'login_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'last_login_at'        => 'datetime',
            'onboarding_completed' => 'boolean',
            'is_admin'             => 'boolean',
            'password'             => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Latest subscription (single object, for dashboard/middleware checks)
     */
    public function subscription()
    {
        return $this->hasOne(\App\Models\Subscription::class)->latestOfMany();
    }

    /**
     * All subscriptions (collection, required by SubscriptionService::activate)
     */
    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Subscription::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SaaS Helpers (VERY IMPORTANT)
    |--------------------------------------------------------------------------
    */

    public function hasActiveSubscription()
    {
        return $this->subscription &&
            $this->subscription->status === 'active' &&
            $this->subscription->ends_at &&
            $this->subscription->ends_at->isFuture();
    }

    public function isTrial()
    {
        return $this->subscription &&
            $this->subscription->status === 'trial' &&
            $this->subscription->trial_ends_at &&
            $this->subscription->trial_ends_at->isFuture();
    }

    public function isExpired()
    {
        return $this->subscription &&
            $this->subscription->ends_at &&
            $this->subscription->ends_at->isPast();
    }
}