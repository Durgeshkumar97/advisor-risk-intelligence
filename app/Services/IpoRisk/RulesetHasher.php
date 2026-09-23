<?php

declare(strict_types=1);

namespace App\Services\IpoRisk;

/**
 * Turns the active ruleset into one sha256 fingerprint.
 *
 * Every number that can move a score — family weights, gate floors, coverage
 * penalties, and the risk-level thresholds this module borrows from
 * config/risk.php — is folded into a canonical form and hashed. Store the
 * hash beside a result and you can prove, later, whether a score that looks
 * different is a different input or a different ruleset.
 *
 * Canonicalisation: associative arrays are key-sorted recursively (as
 * strings, so the order cannot follow PHP's numeric-string coercion), list
 * arrays keep their order because their order is their meaning, then the
 * whole thing is json_encode()d. Key order in the config file therefore does
 * not change the hash; a single threshold does.
 */
final class RulesetHasher
{
    /**
     * Config keys that live outside config/ipo_risk.php but still change what
     * this module outputs. `bound_config_keys` names them; the risk-level
     * thresholds are there because the module calls
     * App\Models\RiskScore::levelFromScore() rather than owning bands itself,
     * and those thresholds are env-overridable.
     */
    public const BOUND_KEY_SLOT = '@bound_config';

    /**
     * Fingerprint of the ruleset currently in config.
     */
    public function hash(): string
    {
        return $this->hashOf($this->ruleset());
    }

    /**
     * The full ruleset as hashed: config/ipo_risk.php plus the resolved value
     * of every borrowed key.
     *
     * @return array<string, mixed>
     */
    public function ruleset(): array
    {
        $ruleset = (array) config('ipo_risk', []);

        $bound = [];

        foreach ((array) config('ipo_risk.bound_config_keys', []) as $key) {
            $bound[(string) $key] = config((string) $key);
        }

        $ruleset[self::BOUND_KEY_SLOT] = $bound;

        return $ruleset;
    }

    /**
     * @param  array<string, mixed>  $ruleset
     */
    public function hashOf(array $ruleset): string
    {
        return hash('sha256', $this->canonicalise($ruleset));
    }

    /**
     * @param  array<string, mixed>  $ruleset
     */
    public function canonicalise(array $ruleset): string
    {
        $this->sortRecursive($ruleset);

        return json_encode(
            $ruleset,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /**
     * @param  array<array-key, mixed>  $ruleset
     */
    private function sortRecursive(array &$ruleset): void
    {
        foreach ($ruleset as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }

        unset($value);

        // A list's order carries meaning (and re-keying it would turn it into
        // a JSON object); only maps get sorted.
        if (! array_is_list($ruleset)) {
            ksort($ruleset, SORT_STRING);
        }
    }
}
