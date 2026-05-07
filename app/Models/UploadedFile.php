<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadedFile extends Model
{
    protected $fillable = [
        'user_id',
        'portfolio_id',
        'original_name',
        'stored_name',
        'file_path',
        'mime_type',
        'file_size',
        'status',
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

    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }
}