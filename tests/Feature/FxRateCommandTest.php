<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\FxRateProvider;
use App\DTOs\FxQuote;
use App\Models\FxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The operator-entered USD rate: validated on entry, kept as history. */
class FxRateCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE = 'Operator entry — bank reference rate';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 10:00:00');
    }

    private function set(string $rate, array $options = []): int
    {
        return $this->artisan('fx:set-usd', ['rate' => $rate] + $options + ['--as-of' => '2026-09-29', '--source' => self::SOURCE])->run();
    }

    private function current(): ?FxQuote
    {
        return app(FxRateProvider::class)->current('USD');
    }

    public function test_it_stores_a_rate_with_its_date_source_and_who_entered_it(): void
    {
        $this->artisan('fx:set-usd', ['rate' => '95.985', '--as-of' => '2026-09-29', '--source' => self::SOURCE, '--by' => 'operator'])
            ->expectsOutputToContain('Stored.')
            ->assertSuccessful();

        $row = FxRate::sole();

        $this->assertSame('USD', $row->currency);
        $this->assertSame('95.985000', $row->rate);
        $this->assertSame('2026-09-29', $row->as_of->toDateString());
        $this->assertSame(self::SOURCE, $row->source);
        $this->assertSame('operator', $row->entered_by);
        $this->assertSame('2026-10-01 10:00:00', $row->entered_at->toDateTimeString());

        $this->assertSame(95.985, $this->current()->rate);
        $this->assertSame(self::SOURCE, $this->current()->source);
    }

    public function test_it_rejects_a_rate_outside_the_sane_range(): void
    {
        foreach (['9.5985', '959.85', '49.99', '200.01', 'abc', '-95'] as $bad) {
            $this->assertSame(1, $this->set($bad), "rate {$bad} should be rejected");
        }

        $this->assertSame(0, FxRate::count());
        $this->assertNull($this->current());

        $this->assertSame(0, $this->set('50'));
        $this->assertSame(0, $this->set('200', ['--force' => true]));
    }

    public function test_it_rejects_a_future_date_a_malformed_date_and_a_missing_source(): void
    {
        $this->assertSame(1, $this->set('95.985', ['--as-of' => '2026-10-02']));
        $this->assertSame(1, $this->set('95.985', ['--as-of' => '29-09-2026']));
        $this->assertSame(1, $this->set('95.985', ['--as-of' => '2026-02-30']));
        $this->assertSame(1, $this->set('95.985', ['--source' => '  ']));
        $this->assertSame(0, FxRate::count());

        $this->assertSame(0, $this->set('95.985', ['--as-of' => '2026-10-01']));   // today is allowed
    }

    public function test_a_rate_more_than_ten_percent_from_the_previous_one_needs_force(): void
    {
        $this->set('95.985');

        // 59.985 is inside the range but is a typo of 95.985.
        $this->assertSame(1, $this->set('59.985', ['--as-of' => '2026-09-30']));
        $this->assertSame(1, FxRate::count());

        $this->assertSame(0, $this->set('104.0', ['--as-of' => '2026-09-30']));                       // +8.4%
        $this->assertSame(0, $this->set('80.0', ['--as-of' => '2026-10-01', '--force' => true]));     // far, but forced
        $this->assertSame(80.0, $this->current()->rate);
    }

    public function test_history_is_kept_and_the_latest_date_then_the_latest_entry_wins(): void
    {
        $this->set('95.985', ['--as-of' => '2026-09-29']);
        $this->set('96.100', ['--as-of' => '2026-09-30']);
        $this->set('95.500', ['--as-of' => '2026-09-25']);     // an older date entered later does not take over
        $this->assertSame(96.1, $this->current()->rate);

        $this->set('96.178', ['--as-of' => '2026-09-30']);     // a correction for the same date does
        $this->assertSame(96.178, $this->current()->rate);

        // Nothing was overwritten.
        $this->assertSame(['95.985000', '96.100000', '95.500000', '96.178000'], FxRate::orderBy('id')->pluck('rate')->all());
    }

    public function test_a_stored_rate_cannot_be_changed_or_deleted(): void
    {
        $this->set('95.985');
        $row = FxRate::sole();

        try {
            $row->update(['rate' => 1]);
            $this->fail('An update was allowed.');
        } catch (\LogicException) {
        }

        try {
            $row->delete();
            $this->fail('A delete was allowed.');
        } catch (\LogicException) {
        }

        $this->assertSame('95.985000', FxRate::sole()->rate);
    }

    public function test_a_quote_is_stale_after_7_days_and_expired_after_31(): void
    {
        $quote = fn (string $asOf) => new FxQuote('USD', 95.985, Carbon::parse($asOf), self::SOURCE);

        $this->assertFalse($quote('2026-09-24')->isStale());     // 7 days
        $this->assertTrue($quote('2026-09-23')->isStale());      // 8 days
        $this->assertFalse($quote('2026-08-31')->isExpired());   // 31 days
        $this->assertTrue($quote('2026-08-30')->isExpired());    // 32 days
    }
}
