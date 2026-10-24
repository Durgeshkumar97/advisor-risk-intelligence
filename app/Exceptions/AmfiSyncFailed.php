<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an AMFI scheme-master sync cannot be trusted: the file could not
 * be fetched, was not NAVAll.txt, or failed a sanity gate. Reported to Sentry
 * so a silently stale scheme master is noticed.
 */
class AmfiSyncFailed extends RuntimeException {}
