<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskScore extends Model
{
    protected $fillable = [
        'user_id',
        'score',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array',
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
}