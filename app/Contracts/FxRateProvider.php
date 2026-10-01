<?php

namespace App\Contracts;

use App\DTOs\FxQuote;

/**
 * Where exchange rates come from. Everything that converts a currency asks
 * this and nothing else, so changing the source of rates (an operator entry
 * today, a licensed feed later) is a change of implementation only.
 *
 * Implementations must not make a network call: this is used while a
 * portfolio file is being parsed.
 */
interface FxRateProvider
{
    /** The rate currently in force for a currency, or null if none is set. */
    public function current(string $currency): ?FxQuote;
}
