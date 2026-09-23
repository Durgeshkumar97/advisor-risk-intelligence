<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Enums;

/**
 * The scoring families, across both state sets.
 *
 * A family owns a share of the weighted score; the factors inside it are
 * Part B's concern. Weights are read from config rather than hard-coded here
 * so that they travel in the ruleset hash.
 *
 * There is no GMP case. Grey-market premium is carried on SentimentPanel and
 * never scored.
 */
enum FactorFamily: string
{
    // --- Pre-listing set (PRE_ISSUE, ALLOTTED_UNLISTED) ---
    case EARNINGS_QUALITY = 'earnings_quality';
    case BALANCE_SHEET = 'balance_sheet';
    case GOVERNANCE = 'governance';
    case ISSUE_STRUCTURE = 'issue_structure';
    case VALUATION = 'valuation';
    case INSTITUTIONAL_DEMAND = 'institutional_demand';

    // --- Listed set (LISTED_HELD) ---
    case LIQUIDITY = 'liquidity';
    case LOCKIN = 'lockin';
    case POST_LISTING_DELIVERY = 'post_listing_delivery';
    case CONCENTRATION = 'concentration';
    case GOVERNANCE_DRIFT = 'governance_drift';
    case PRICE_DRIFT = 'price_drift';

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function label(): string
    {
        return match ($this) {
            self::EARNINGS_QUALITY => 'Earnings quality',
            self::BALANCE_SHEET => 'Balance sheet',
            self::GOVERNANCE => 'Governance',
            self::ISSUE_STRUCTURE => 'Issue structure',
            self::VALUATION => 'Valuation',
            self::INSTITUTIONAL_DEMAND => 'Institutional demand',
            self::LIQUIDITY => 'Liquidity',
            self::LOCKIN => 'Lock-in',
            self::POST_LISTING_DELIVERY => 'Post-listing delivery',
            self::CONCENTRATION => 'Concentration',
            self::GOVERNANCE_DRIFT => 'Governance drift',
            self::PRICE_DRIFT => 'Price drift',
        };
    }

    /**
     * Which `ipo_risk.families` key this family is weighted under.
     */
    public function group(): string
    {
        return match ($this) {
            self::EARNINGS_QUALITY, self::BALANCE_SHEET, self::GOVERNANCE,
            self::ISSUE_STRUCTURE, self::VALUATION, self::INSTITUTIONAL_DEMAND => 'pre_listing',

            self::LIQUIDITY, self::LOCKIN, self::POST_LISTING_DELIVERY,
            self::CONCENTRATION, self::GOVERNANCE_DRIFT, self::PRICE_DRIFT => 'listed',
        };
    }

    public function weight(): int
    {
        return (int) config("ipo_risk.families.{$this->group()}.{$this->value}", 0);
    }

    /**
     * The families that apply to a state, in config order.
     *
     * @return array<int, self>
     */
    public static function forState(IpoState $state): array
    {
        $group = $state->familyGroup();

        return array_values(array_filter(
            self::cases(),
            static fn (self $family): bool => $family->group() === $group,
        ));
    }
}
