<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Enums;

/**
 * What the holder is structurally able to do — a statement of fact about the
 * instrument, not a suggestion about what to do with it.
 *
 * Always derived through IpoState::actionability(); never set by hand and
 * never inferred from a score.
 */
enum Actionability: string
{
    case NOT_YET_COMMITTED = 'not_yet_committed';
    case NO_EXIT_AVAILABLE = 'no_exit_available';
    case EXIT_AVAILABLE = 'exit_available';

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function label(): string
    {
        return match ($this) {
            self::NOT_YET_COMMITTED => 'No capital committed yet',
            self::NO_EXIT_AVAILABLE => 'No exit available until listing',
            self::EXIT_AVAILABLE => 'Exit available on the exchange',
        };
    }
}
