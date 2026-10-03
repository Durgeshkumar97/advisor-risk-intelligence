<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The market multiplier scales every risk score, so the tests must not take
 * it from whatever a developer's .env happens to say. Laravel loads .env in
 * the test process; phpunit.xml pins the value so that .env cannot matter.
 *
 * (A variable exported in the shell that runs the tests still wins, as it
 * does for every other phpunit.xml entry: Laravel reads the real environment
 * before anything PHPUnit sets.)
 */
class MarketMultiplierPinTest extends TestCase
{
    /** The RISK_MARKET_MULTIPLIER entry of phpunit.xml, or null if there is none. */
    private function pin(): ?\SimpleXMLElement
    {
        $xml = simplexml_load_file(base_path('phpunit.xml'));

        foreach ($xml->php->env as $env) {
            if ((string) $env['name'] === 'RISK_MARKET_MULTIPLIER') {
                return $env;
            }
        }

        return null;
    }

    public function test_phpunit_pins_the_market_multiplier(): void
    {
        $pin = $this->pin();

        $this->assertNotNull($pin, 'phpunit.xml does not pin RISK_MARKET_MULTIPLIER: tests would read it from .env.');
        $this->assertTrue(is_numeric((string) $pin['value']));
    }

    public function test_the_multiplier_the_tests_run_with_is_the_pinned_one(): void
    {
        $this->assertSame((float) (string) $this->pin()['value'], config('risk.market_multiplier'));
    }

    /** config/risk.php's own default: what applies when no environment sets the variable. */
    private function configDefault(): float
    {
        $key = 'RISK_MARKET_MULTIPLIER';
        $saved = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];

        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);

        try {
            return (require config_path('risk.php'))['market_multiplier'];
        } finally {
            if ($saved[0] !== null) {
                $_ENV[$key] = $saved[0];
            }
            if ($saved[1] !== null) {
                $_SERVER[$key] = $saved[1];
            }
            if ($saved[2] !== false) {
                putenv($key.'='.$saved[2]);
            }
        }
    }

    public function test_the_default_market_multiplier_is_neutral(): void
    {
        // No market snapshot is being produced, so there is no measurement to
        // adjust a score by. 1.0 leaves the score as the four factors give it.
        $this->assertSame(1.0, $this->configDefault());
    }

    public function test_the_pin_is_the_config_default_so_recorded_scores_track_the_default(): void
    {
        // Change one without the other and the scores the tests record are no
        // longer the scores a fresh install produces.
        $this->assertSame($this->configDefault(), (float) (string) $this->pin()['value']);
    }
}
