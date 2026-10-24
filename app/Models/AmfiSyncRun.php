<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The outcome of one amfi:sync run, kept so the next run can compare against it. */
class AmfiSyncRun extends Model
{
    protected $fillable = ['source', 'status', 'rows_parsed', 'file_date', 'error'];

    protected $casts = [
        'file_date' => 'date',
    ];

    public static function lastSuccessful(): ?self
    {
        return static::query()->where('status', 'success')->latest('id')->first();
    }
}
