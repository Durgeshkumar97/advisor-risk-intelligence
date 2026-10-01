<?php

namespace App\Services\Fx;

use App\Contracts\FxRateProvider;
use App\DTOs\FxQuote;
use App\Models\FxRate;

/**
 * Rates entered by the operator with `php artisan fx:set-usd`.
 *
 * The rate in force is the one with the latest as_of date; when two rows share
 * a date, the one entered last wins, so a correction replaces a mistake
 * without the mistake being erased.
 */
class DatabaseFxRateProvider implements FxRateProvider
{
    public function current(string $currency): ?FxQuote
    {
        $row = FxRate::where('currency', strtoupper($currency))
            ->orderByDesc('as_of')
            ->orderByDesc('id')
            ->first();

        return $row === null
            ? null
            : new FxQuote($row->currency, (float) $row->rate, $row->as_of, $row->source);
    }
}
