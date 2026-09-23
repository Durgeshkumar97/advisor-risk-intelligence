<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Enums;

/**
 * Where the investor stands relative to the issue.
 *
 * This is the module's root fact: it selects which family set scores, and it
 * alone determines what the holder can actually do (see actionability()).
 */
enum IpoState: string
{
    /** The issue is open or upcoming; no money is committed. */
    case PRE_ISSUE = 'pre_issue';

    /** Shares are allotted but not yet listed — held, and unsellable. */
    case ALLOTTED_UNLISTED = 'allotted_unlisted';

    /** Listed and trading. */
    case LISTED_HELD = 'listed_held';

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function label(): string
    {
        return match ($this) {
            self::PRE_ISSUE => 'Pre-issue',
            self::ALLOTTED_UNLISTED => 'Allotted, not yet listed',
            self::LISTED_HELD => 'Listed and held',
        };
    }

    /**
     * Actionability is derived from state and from nothing else.
     *
     * In particular it is never derived from the score: an allotted-unlisted
     * holding has no exit at a risk score of 3 and no exit at 97, because
     * there is no market to exit into. Reading it off the number would invent
     * an option the holder does not have.
     */
    public function actionability(): Actionability
    {
        return match ($this) {
            self::PRE_ISSUE => Actionability::NOT_YET_COMMITTED,
            self::ALLOTTED_UNLISTED => Actionability::NO_EXIT_AVAILABLE,
            self::LISTED_HELD => Actionability::EXIT_AVAILABLE,
        };
    }

    /**
     * Which key under `ipo_risk.families` holds this state's weights.
     *
     * Pre-issue and allotted-unlisted share the pre-listing set: neither has
     * a trading history to score against.
     */
    public function familyGroup(): string
    {
        return match ($this) {
            self::PRE_ISSUE, self::ALLOTTED_UNLISTED => 'pre_listing',
            self::LISTED_HELD => 'listed',
        };
    }
}
