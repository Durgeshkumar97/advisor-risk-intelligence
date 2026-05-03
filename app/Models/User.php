<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Subscription;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
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