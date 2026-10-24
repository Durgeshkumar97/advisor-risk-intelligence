<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised (via report(), never thrown) when amfi:sync meets an AMFI category
 * header that config/fund_categories.php does not list. Holdings in that
 * category are scored as 'other' until the header is added to the config, so
 * Sentry hears about it once per category per sync.
 */
class UnknownAmfiCategory extends RuntimeException {}
