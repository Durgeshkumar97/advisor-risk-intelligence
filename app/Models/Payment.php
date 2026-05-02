<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id','payment_id','user_id','email','phone',
        'plan','amount','status','meta'
    ];

    protected $casts = [
        'meta' => 'array'
    ];
}