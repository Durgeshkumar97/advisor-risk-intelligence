<?php

namespace Tests\Feature;

use App\DTOs\SchemeMatch;
use App\Models\MfScheme;
use App\Services\Amfi\SchemeMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Holding → AMFI scheme matching against the real-row fixture NAVAll.txt.
 */
class SchemeMatcherTest extends TestCase
{
    use RefreshDatabase;

    private SchemeMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['amfi.min_rows' => 5]);
        $this->travelTo(Carbon::parse('2026-10-24 06:30', 'Asia/Kolkata'));
        $this->artisan('amfi:sync', ['--source' => base_path('tests/Fixtures/amfi/NAVAll.txt')])->assertSuccessful();

        $this->matcher = app(SchemeMatcher::class);
    }

    private function scheme(string $code, string $name, string $broad, string $sub, string $navDate = '2026-10-23'): void
    {
        $normalizer = app(\App\Services\Amfi\SchemeNameNormalizer::class);

        MfScheme::create([
            'scheme_code' => $code, 'scheme_name' => $name, 'normalized_name' => $normalizer->normalize($name),
            'amc' => 'Test Mutual Fund', 'scheme_type' => 'open', 'broad_category' => $broad, 'sub_category' => $sub,
            'latest_nav' => 10, 'nav_date' => $navDate,
        ]);
    }

    public function test_isin_matches_either_isin_column_exactly(): void
    {
        $growth = $this->matcher->match('INF179KA1IZ7', 'anything at all');
        $reinvest = $this->matcher->match(' inf179ka1jb6 ', 'anything at all');

        $this->assertSame([SchemeMatch::METHOD_ISIN, 1.0, '128629'], [$growth->method, $growth->confidence, $growth->scheme->scheme_code]);
        $this->assertSame([SchemeMatch::METHOD_ISIN, '128626'], [$reinvest->method, $reinvest->scheme->scheme_code]);
    }

    public function test_a_repeated_isin_resolves_to_the_scheme_with_the_newest_nav(): void
    {
        // INF204KA1UU2 is on two matured FMPs in AMFI's own file: 131348 (Sep 2017) and 131537 (Oct 2017).
        $this->assertSame('131537', $this->matcher->match('INF204KA1UU2', '')->scheme->scheme_code);
    }

    public function test_an_unknown_isin_falls_through_to_the_name(): void
    {
        $match = $this->matcher->match('INF000000000', 'HDFC Corporate Bond Fund - Direct Plan - Growth');

        $this->assertSame(SchemeMatch::METHOD_EXACT, $match->method);
        $this->assertSame('Corporate Bond Fund', $match->scheme->sub_category);
    }

    public function test_banking_and_psu_spelled_with_ampersand_or_and_both_match_exactly(): void
    {
        foreach (['HDFC Banking & PSU Debt Fund - Regular Plan - IDCW', 'HDFC Banking and PSU Debt Fund Direct Growth', 'Axis Banking & PSU Debt Fund'] as $name) {
            $match = $this->matcher->match(null, $name);

            $this->assertSame(SchemeMatch::METHOD_EXACT, $match->method, $name);
            $this->assertSame(1.0, $match->confidence, $name);
            $this->assertStringContainsString('Banking and PSU', $match->scheme->sub_category, $name);
        }
    }

    public function test_an_old_sebi_debt_category_name_matches_the_renamed_scheme(): void
    {
        // AMFI lists "Axis Dynamic Term Fund"; an older statement says "Dynamic Bond".
        $match = $this->matcher->match(null, 'Axis Dynamic Bond Fund - Direct Growth');

        $this->assertSame('120451', $match->scheme->scheme_code);
    }

    public function test_a_former_name_in_brackets_matches_exactly(): void
    {
        $match = $this->matcher->match(null, 'ICICI Prudential Bluechip Fund - Direct Plan - IDCW');

        $this->assertSame(SchemeMatch::METHOD_EXACT, $match->method);
        $this->assertSame('Large Cap Fund', $match->scheme->sub_category);
    }

    public function test_a_live_scheme_is_preferred_over_a_dead_one_of_the_same_name(): void
    {
        // 108467 (Apr 2020) and 120585 (Oct 2026) share the name.
        $this->assertSame('120585', $this->matcher->match(null, 'ICICI Prudential Large Cap Fund')->scheme->scheme_code);
    }

    public function test_a_typo_matches_fuzzily_and_is_usable(): void
    {
        $match = $this->matcher->match(null, 'Parag Parikh Flexi Cap Fnud Direct Growth');

        $this->assertSame(SchemeMatch::METHOD_FUZZY, $match->method);
        $this->assertTrue($match->isUsable());
        $this->assertSame('122639', $match->scheme->scheme_code);
    }

    public function test_nifty_50_never_matches_nifty_next_50(): void
    {
        $this->scheme('900001', 'UTI Nifty Next 50 Index Fund', 'Other Scheme', 'Index Funds');

        $next = $this->matcher->match(null, 'UTI Nifty Next 50 Index Fnd');
        $fifty = $this->matcher->match(null, 'UTI Nifty 50 Index Fnd');

        $this->assertSame('900001', $next->scheme->scheme_code);
        $this->assertSame('120716', $fifty->scheme?->scheme_code);
    }

    public function test_a_different_index_number_is_never_a_match(): void
    {
        $match = $this->matcher->match(null, 'Motilal Oswal Nifty Midcap 50 Index Fund');

        $this->assertSame(SchemeMatch::METHOD_NONE, $match->method);
        $this->assertNull($match->scheme);
    }

    public function test_two_near_equal_candidates_in_different_categories_are_no_match(): void
    {
        $this->scheme('900002', 'Acme Alpha Prime Fund', 'Equity Scheme', 'Flexi Cap Fund');
        $this->scheme('900003', 'Acme Alpha Prima Fund', 'Hybrid Scheme', 'Aggressive Hybrid Fund');

        $match = $this->matcher->match(null, 'Acme Alpha Primo Fund');

        $this->assertSame(SchemeMatch::METHOD_NONE, $match->method);
    }

    public function test_an_invented_fund_name_is_unmatched(): void
    {
        $match = $this->matcher->match(null, 'HDFC Quantum Leap Opportunities Fund');

        $this->assertSame(SchemeMatch::METHOD_NONE, $match->method);
        $this->assertFalse($match->isUsable());
    }

    public function test_confidence_bands(): void
    {
        $this->assertFalse((new SchemeMatch(MfScheme::find('122639'), SchemeMatch::METHOD_FUZZY, 0.79))->isUsable());
        $this->assertTrue((new SchemeMatch(MfScheme::find('122639'), SchemeMatch::METHOD_FUZZY, 0.80))->isProbable());
        $this->assertFalse((new SchemeMatch(MfScheme::find('122639'), SchemeMatch::METHOD_FUZZY, 0.90))->isProbable());
    }

    public function test_only_funds_and_fund_isins_are_looked_up(): void
    {
        $this->assertTrue(SchemeMatcher::applies(['asset_type' => 'mutual_fund', 'isin' => null]));
        $this->assertTrue(SchemeMatcher::applies(['asset_type' => 'etf', 'isin' => null]));
        $this->assertTrue(SchemeMatcher::applies(['asset_type' => 'stock', 'isin' => 'INF204KB17I5']));   // GOLDBEES in an equity export
        $this->assertFalse(SchemeMatcher::applies(['asset_type' => 'stock', 'isin' => 'INE002A01018']));
        $this->assertFalse(SchemeMatcher::applies(['asset_type' => 'stock', 'isin' => null]));
    }
}
