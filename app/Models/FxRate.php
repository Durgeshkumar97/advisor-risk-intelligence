<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One operator-entered exchange rate: rupees per 1 unit of `currency`.
 *
 * Insert-only. A correction is a new row; the old one stays, because reports
 * already generated name the rate they used.
 */
class FxRate extends Model
{
    public $timestamps = false;

    protected $fillable = ['currency', 'rate', 'as_of', 'source', 'entered_by', 'entered_at'];

    protected $casts = [
        'rate' => 'decimal:6',
        'as_of' => 'date',
        'entered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $refuse = fn () => throw new \LogicException('Exchange rates are history: add a new rate instead of changing or deleting one.');

        static::updating($refuse);
        static::deleting($refuse);
    }
}
