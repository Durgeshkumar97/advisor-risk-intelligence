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
}
