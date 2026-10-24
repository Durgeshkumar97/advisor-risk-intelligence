<?php

namespace Tests\Feature;

use App\Exceptions\UnknownAmfiCategory;
use App\Jobs\ProcessPortfolioFile;
use App\Models\AmfiSyncRun;
use App\Models\MfScheme;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use App\Services\ReportProvenance;
use App\Services\RiskEngine\AssetRiskScorer;
use App\Services\RiskEngine\PortfolioRiskCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A fund holding is scored and counted as equity from what it IS (its AMFI
 * category, or failing that its name), not from a flat "mutual fund = equity"
 * rule. All holdings are funds in the AMFI fixture; names are public scheme
 * names, values invented.
 */
class FundClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['amfi.min_rows' => 5]);
        $this->travelTo(Carbon::parse('2026-10-24 06:30', 'Asia/Kolkata'));
        $this->artisan('amfi:sync', ['--source' => base_path('tests/Fixtures/amfi/NAVAll.txt')])->assertSuccessful();
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: string}>  $holdings  name, type, isin
     * @return array{0: \Illuminate\Support\Collection, 1: RiskScore}
     */
    private function process(array $holdings): array
    {
        $csv = "name,asset_type,isin,current_value\n";
        foreach ($holdings as $h) {
            $csv .= "{$h[0]},{$h[1]},".($h[2] ?? '').",10000\n";
        }

        Storage::fake('portfolios');
        Mail::fake();

        $user = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Test Portfolio']);
        Storage::disk('portfolios')->put('uploads/portfolio.csv', $csv);

        ProcessPortfolioFile::dispatchSync(PortfolioFile::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio->id,
            'original_name' => 'portfolio.csv',
            'stored_name' => 'portfolio.csv',
            'path' => 'uploads/portfolio.csv',
            'mime_type' => 'text/csv',
            'file_size' => strlen($csv),
            'status' => PortfolioFile::STATUS_PENDING,
        ]));

        return [
            PortfolioAsset::where('portfolio_id', $portfolio->id)->orderBy('id')->get(),
            RiskScore::where('portfolio_id', $portfolio->id)->firstOrFail(),
        ];
    }

    public function test_a_debt_only_portfolio_has_no_equity_and_is_not_equity_heavy(): void
    {
        [$assets, $score] = $this->process([
            ['HDFC Corporate Bond Fund', 'mutual_fund'],
            ['Franklin India Dynamic Accrual Fund - Direct Growth', 'mutual_fund'],
            ['Kotak Medium Term Fund', 'mutual_fund'],
            ['ICICI Prudential Credit Risk Fund', 'mutual_fund'],
            ['Axis Banking and PSU Debt Fund', 'mutual_fund'],
        ]);

        $this->assertSame(0.0, (float) $score->meta['equity_ratio_pct']);
        $this->assertNotContains('EQUITY_HEAVY', $score->meta['risk_flags']);
        $this->assertCount(5, $assets);
        foreach ($assets as $asset) {
            $this->assertSame('debt', $asset->meta['classification']['asset_class'], $asset->name);
        }
    }

    public function test_banking_and_psu_debt_funds_are_debt_not_sectoral(): void
    {
        [$assets] = $this->process([
            ['HDFC Banking & PSU Debt Fund - Direct Growth', 'mutual_fund'],
            ['Kotak Banking and PSU Debt Fund', 'mutual_fund'],
        ]);

        foreach ($assets as $asset) {
            $this->assertSame(['debt', 'banking_psu'], [$asset->meta['classification']['asset_class'], $asset->meta['classification']['category']], $asset->name);
        }
    }

    public function test_a_midcap_index_fund_scores_above_a_nifty_50_index_fund(): void
    {
        [$mid, $midScore] = $this->process([['Motilal Oswal Nifty Midcap 150 Index Fund', 'mutual_fund']]);
        [$large, $largeScore] = $this->process([['UTI Nifty 50 Index Fund', 'mutual_fund']]);

        $this->assertSame('mid_cap', $mid[0]->meta['classification']['category']);
        $this->assertSame('large_cap', $large[0]->meta['classification']['category']);
        $this->assertGreaterThan((float) $large[0]->risk_score, (float) $mid[0]->risk_score);
        $this->assertGreaterThan((float) $largeScore->score, (float) $midScore->score);
    }

    public function test_gold_funds_are_commodity_with_no_equity(): void
    {
        [$assets, $score] = $this->process([
            ['Nippon India ETF Gold BeES', 'etf'],
            ['Gold ETF units', 'stock', 'INF204KB17I5'],
            ['SBI Gold Fund', 'mutual_fund'],
        ]);

        foreach ($assets as $asset) {
            $this->assertEquals(['commodity', 0.0], [$asset->meta['classification']['asset_class'], $asset->meta['classification']['equity_share']], $asset->name);
        }

        // The ISIN-only holding was typed as a stock; it is stored as the ETF it is.
        $this->assertSame('etf', $assets[1]->asset_type);
        $this->assertArrayNotHasKey('stock_risk', $assets[1]->meta);
        $this->assertSame(0.0, (float) $score->meta['equity_ratio_pct']);
    }

    public function test_a_sectoral_fund_scores_above_a_flexi_cap_and_above_the_old_generic_fund_score(): void
    {
        [$assets] = $this->process([
            ['ICICI Prudential Banking & Financial Services Fund', 'mutual_fund'],
            ['Parag Parikh Flexi Cap Fund', 'mutual_fund'],
        ]);

        $this->assertSame('sectoral', $assets[0]->meta['classification']['category']);
        $this->assertGreaterThan((float) $assets[1]->risk_score, (float) $assets[0]->risk_score);
        $this->assertGreaterThan(45.0, (float) $assets[0]->risk_score);
    }

    public function test_an_unmatched_fund_keeps_its_keyword_score_and_is_classified_by_name(): void
    {
        [$assets] = $this->process([['Acme Moonshot Midcap Fund', 'mutual_fund']]);
        $asset = $assets[0];

        $this->assertSame('none', $asset->match_method);
        $this->assertEqualsWithDelta((new AssetRiskScorer)->score('mutual_fund', 'Acme Moonshot Midcap Fund')['score'], (float) $asset->risk_score, 0.001);
        $this->assertSame('name', $asset->meta['classification']['basis']);
        $this->assertNull($asset->meta['classification']['amfi_category']);
        $this->assertSame('classified by name', ReportProvenance::scoreBasisLabel($asset));
    }

    public function test_an_unmatched_short_duration_fund_is_debt(): void
    {
        [$assets, $score] = $this->process([['Acme Short Duration Fund', 'mutual_fund']]);

        $this->assertSame('debt', $assets[0]->meta['classification']['asset_class']);
        $this->assertSame(0.0, (float) $score->meta['equity_ratio_pct']);
    }

    public function test_a_hybrid_counts_its_equity_share_only(): void
    {
        [, $aggressive] = $this->process([['ICICI Prudential Aggressive Hybrid Fund', 'mutual_fund']]);
        [, $arbitrage] = $this->process([['Kotak Arbitrage Fund', 'mutual_fund']]);

        $this->assertSame(72.5, (float) $aggressive->meta['equity_ratio_pct']);
        $this->assertSame(0.0, (float) $arbitrage->meta['equity_ratio_pct']);
    }

    public function test_an_unknown_amfi_category_is_reported_once_and_scored_as_other(): void
    {
        Exceptions::fake();

        $file = tempnam(sys_get_temp_dir(), 'amfi');
        file_put_contents($file, implode("\n", [
            'Scheme Code;ISIN Div Payout/ ISIN Growth;ISIN Div Reinvestment;Scheme Name;Plan;Option;Net Asset Value;Date',
            '',
            'Open Ended Schemes(Brand New Scheme - Moon Fund)',
            '',
            'Example Mutual Fund',
            '',
            '900001;INF999Z01AB1;-;Example Moon Fund;Direct Plan;Growth;10.0000;23-Oct-2026',
            '900002;INF999Z01AC9;-;Example Moon Fund Two;Direct Plan;Growth;10.0000;23-Oct-2026',
            '',
        ]));

        // Start from an empty scheme master so the row-drop gate does not apply.
        MfScheme::query()->delete();
        AmfiSyncRun::query()->delete();
        config(['amfi.min_rows' => 1]);
        $this->artisan('amfi:sync', ['--source' => $file])->assertSuccessful();
        @unlink($file);

        Exceptions::assertReported(fn (UnknownAmfiCategory $e) => str_contains($e->getMessage(), 'Brand New Scheme - Moon Fund')
            && str_contains($e->getMessage(), 'other'));
        Exceptions::assertReportedCount(1);

        [$assets] = $this->process([['Example Moon Fund', 'mutual_fund', 'INF999Z01AB1']]);

        $this->assertSame(['other', 'other'], [$assets[0]->meta['classification']['category'], $assets[0]->meta['classification']['asset_class']]);
    }

    public function test_a_legacy_holding_without_a_classification_keeps_the_old_equity_rule(): void
    {
        $asset = fn (string $type, float $score, array $meta = []) => (new PortfolioAsset)->forceFill([
            'asset_type' => $type, 'name' => 'X', 'quantity' => 1, 'current_value' => 1000, 'invested_value' => 1000,
            'profit_loss' => 0, 'risk_score' => $score, 'risk_level' => 'MEDIUM', 'meta' => $meta,
        ]);

        $result = (new PortfolioRiskCalculator)->calculate(collect([
            $asset('mutual_fund', 45),                                      // old rule: counts in full
            $asset('mutual_fund', 5),                                       // old rule: below 25, does not count
            $asset('bond', 30),                                             // not an equity type
            $asset('mutual_fund', 45, ['classification' => ['equity_share' => 0.5]]),   // new rule: half
        ]));

        $this->assertSame(37.5, $result['meta']['equity_ratio_pct']);       // (1000 + 500) / 4000
    }
}
