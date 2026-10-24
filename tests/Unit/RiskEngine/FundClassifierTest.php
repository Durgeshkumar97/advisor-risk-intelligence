<?php

namespace Tests\Unit\RiskEngine;

use App\Models\MfScheme;
use App\Services\RiskEngine\FundClassifier;
use Tests\TestCase;

class FundClassifierTest extends TestCase
{
    private function classifier(): FundClassifier
    {
        return app(FundClassifier::class);
    }

    private function scheme(string $type, string $broad, ?string $sub, string $name): MfScheme
    {
        return (new MfScheme)->forceFill([
            'scheme_type' => $type, 'broad_category' => $broad, 'sub_category' => $sub, 'scheme_name' => $name,
        ]);
    }

    public function test_category_key_straightens_quotes_collapses_space_and_drops_asterisks(): void
    {
        $this->assertSame("open: children's fund - childrens' fund", FundClassifier::categoryKey('open', 'Children’s Fund', "Childrens' Fund"));
        $this->assertSame('open: other scheme - other etfs', FundClassifier::categoryKey('open', 'Other Scheme', 'Other  ETFs'));
        $this->assertSame('open: solution oriented schemes - retirement fund', FundClassifier::categoryKey('open', 'Solution Oriented Schemes **', 'Retirement Fund'));
        $this->assertSame('close: income', FundClassifier::categoryKey('close', 'Income', null));
    }

    public function test_every_category_in_the_config_resolves_to_a_class_or_a_valid_scope(): void
    {
        $classes = config('fund_categories.classes');
        $scopes = config('fund_categories.scopes');

        foreach (config('fund_categories.categories') as $key => $target) {
            if (str_starts_with($target, 'by_name:')) {
                $this->assertArrayHasKey(substr($target, 8), $scopes, $key);
            } else {
                $this->assertArrayHasKey($target, $classes, $key);
            }
        }

        foreach ($scopes as $name => $scope) {
            $this->assertArrayHasKey($scope['default'], $classes, "scope {$name}");
        }

        foreach (config('fund_categories.name_rules') as [$pattern, $slug]) {
            $this->assertArrayHasKey($slug, $classes, $pattern);
            $this->assertNotFalse(@preg_match($pattern, ''), $pattern);
        }
    }

    public function test_category_keys_of_config_are_in_canonical_form(): void
    {
        foreach (array_keys(config('fund_categories.categories')) as $key) {
            $this->assertSame($key, mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', str_replace(['*', "\u{2019}"], ['', "'"], $key)))));
        }
    }

    public function test_an_unknown_category_is_other_and_known_ones_are_known(): void
    {
        $unknown = $this->classifier()->classifyScheme($this->scheme('open', 'Brand New Scheme', 'Moon Fund', 'Moon Fund'));
        $this->assertSame(['other', 'other', 45.0, 0.0], [$unknown['category'], $unknown['asset_class'], $unknown['score'], $unknown['equity_share']]);

        $this->assertTrue($this->classifier()->isKnownCategory('open: equity scheme - large cap fund'));
        $this->assertFalse($this->classifier()->isKnownCategory('open: brand new scheme - moon fund'));
    }

    public function test_a_scope_restricts_what_a_name_can_decide(): void
    {
        $c = $this->classifier();

        // An equity index fund with "gilt" in its name stays equity.
        $r = $c->classifyScheme($this->scheme('open', 'Index Funds', 'Equity Funds', 'Example Gilt Nifty 50 Index Fund'));
        $this->assertSame('equity', $r['asset_class']);

        // Nothing in scope settles it: the scope default.
        $r = $c->classifyScheme($this->scheme('open', 'Index Funds', 'Equity Funds', 'Example Mystery Index Fund'));
        $this->assertSame('equity_other', $r['category']);
    }

    public function test_names_in_the_unrestricted_buckets(): void
    {
        $c = $this->classifier();
        $other = fn (string $name) => $c->classifyScheme($this->scheme('open', 'Other Scheme', 'Other  ETFs', $name))['category'];
        $etf = fn (string $name) => $c->classifyScheme($this->scheme('open', 'Exchange Traded Funds (ETFs)', 'Other ETF', $name))['category'];
        $index = fn (string $name) => $c->classifyScheme($this->scheme('open', 'Other Scheme', 'Index Funds', $name))['category'];

        $this->assertSame('overnight', $other('Nippon India ETF Nifty 1D Rate Liquid BeES'));
        $this->assertSame('debt_index', $other('Bharat Bond ETF - April 2030'));
        $this->assertSame('mid_cap', $etf('Groww Nifty Midcap 150 ETF'));
        $this->assertSame('large_mid', $index('Example Nifty Next 50 Index Fund'));
        $this->assertSame('large_cap', $index('Example Nifty 50 Index Fund'));
        $this->assertSame('gold', $other('Nippon India ETF Gold BeES'));
    }

    public function test_class_name_rules_for_unmatched_funds(): void
    {
        $c = $this->classifier();

        $this->assertSame('elss', $c->classifyName('SBI Long Term Equity Fund')['category']);
        $this->assertSame('equity_savings', $c->classifyName('Kotak Equity Savings Fund')['category']);
        $this->assertSame('banking_psu', $c->classifyName('HDFC Banking and PSU Debt Fund')['category']);
        $this->assertSame('sectoral', $c->classifyName('ICICI Prudential Banking and Financial Services')['category']);
        $this->assertSame('equity_other', $c->classifyName('Acme Moonshot Fund')['category']);
        $this->assertSame('commodity', $c->classifyName('Acme Silver Fund')['asset_class']);
        $this->assertSame('debt', $c->classifyName('Acme Short Duration Fund')['asset_class']);
        $this->assertSame('hybrid', $c->classifyName('Acme Hybrid Fund')['asset_class']);
        $this->assertSame('debt', $c->classifyName('Franklin India Dynamic Accrual Fund')['asset_class']);
        $this->assertSame('debt', $c->classifyName('Aditya Birla Sun Life Savings Fund')['asset_class']);
    }
}
