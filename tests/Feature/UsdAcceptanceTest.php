<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\FxRate;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use App\Services\ReportProvenance;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BrokerExportFixtures as Fixtures;
use Tests\TestCase;

/**
 * Acceptance test for US-dollar holdings: a ZIP of two client folders, shaped
 * like the upload this feature was built for. Every name and number is invented.
 *
 *   clients.zip
 *   ├── Asha Rao/
 *   │   ├── groww-holdings.xlsx       Groww layout, header on row 10, 3 ELSS funds, rupees
 *   │   └── indmoney-us-stocks.xls    BIFF8, header on row 8, "($)" columns, 16 tickers,
 *   │                                 Total Value = Quantity × Avg. Price (a cost, not a market value)
 *   └── Vikram Rao/
 *       ├── groww-holdings.xlsx       two ELSS funds
 *       └── other-broker.csv          the first fund again (same ISIN, different name) + a liquid fund
 *
 * Exchange rate for the test: ₹95.985 per USD, as of 29 Sep 2026.
 */
class UsdAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Vikram Rao's rows and score as main (6a40e82) produced them, before this phase.
     *
     * One deliberate change since: the default market multiplier went from 1.05 to
     * 1.0 in Oct 2026, so the score is 44.40 (was 46.62) and market_multiplier is 1.
     * Rows, factor scores, flags and risk level are as recorded.
     */
    private const RUPEE_CLIENT_ROWS_BEFORE = '[{"name":"Example ELSS Tax Saver Fund Direct Growth","asset_type":"mutual_fund","symbol":null,"isin":"INF000TEST11","quantity":"200.0000","buy_price":"300.00","current_price":"310.00","invested_value":"60000.00","current_value":"62000.00","profit_loss":"2000.00","risk_score":"45.00","risk_level":"MEDIUM","meta":{"source_file_id":2,"invested_value_source":"file","sources":[{"source_file":"groww-holdings.xlsx","cost_known":true,"as_of":null},{"source_file":"other-broker.csv","cost_known":true,"as_of":null}],"currency":"INR","value_basis":"market","cost_known":true,"as_of":null}},{"name":"Example Tax Advantage Fund Direct Growth","asset_type":"mutual_fund","symbol":null,"isin":"INF000TEST12","quantity":"300.0000","buy_price":"100.00","current_price":"105.00","invested_value":"30000.00","current_value":"31500.00","profit_loss":"1500.00","risk_score":"45.00","risk_level":"MEDIUM","meta":{"source_file_id":2,"invested_value_source":"file","sources":[{"source_file":"groww-holdings.xlsx","cost_known":true,"as_of":null}],"currency":"INR","value_basis":"market","cost_known":true,"as_of":null}},{"name":"Example Liquid Fund","asset_type":"mutual_fund","symbol":null,"isin":"INF000TEST13","quantity":"3.0000","buy_price":"1000.00","current_price":"1033.33","invested_value":"3000.00","current_value":"3100.00","profit_loss":"100.00","risk_score":"17.00","risk_level":"LOW","meta":{"source_file_id":2,"invested_value_source":"file","sources":[{"source_file":"other-broker.csv","cost_known":true,"as_of":null}],"currency":"INR","value_basis":"market","cost_known":true,"as_of":null}}]';

    private const RUPEE_CLIENT_SCORE_BEFORE = '{"score":"44.40","volatility":"16.17","drawdown":"0.00","meta":{"composition_score":44.1,"concentration_score":27.89,"equity_ratio_score":96.79,"drawdown_score":0,"equity_ratio_pct":96.8,"hhi":0.5193,"asset_count":3,"dominant_asset_type":"mutual_fund","total_invested":93000,"total_current":96600,"market_multiplier":1,"risk_flags":["EQUITY_HEAVY","LOW_DIVERSIFICATION"],"risk_level":"MEDIUM","stock_risk_fallback_count":0,"calculator_version":"portfolio-risk-calculator-v1","trigger":"file_upload","next_action":"Equity allocation is very high, with limited debt or hybrid exposure."}}';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 10:00:00');
        Storage::fake('portfolios');
        Mail::fake();

        $this->mock(StockRiskService::class)->shouldReceive('classifyBatch')->andReturn([]);

        $this->user = User::factory()->create();
    }

    private static function rupeeClientFiles(): array
    {
        return [
            'Vikram Rao/groww-holdings.xlsx' => fn (string $path) => Fixtures::growwFunds($path, [
                ['Example ELSS Tax Saver Fund Direct Growth', 'INF000TEST11', 120, 36000, 37200],
                ['Example Tax Advantage Fund Direct Growth', 'INF000TEST12', 300, 30000, 31500],
            ]),
            'Vikram Rao/other-broker.csv' => "Scheme Name,ISIN,Units,Invested Value,Current Value\n"
                ."EXAMPLE ELSS TAX SAVER - DIRECT PLAN,INF000TEST11,80,24000,24800\n"
                ."Example Liquid Fund,INF000TEST13,3,3000,3100\n",
        ];
    }

    private static function dollarClientFiles(): array
    {
        return [
            'Asha Rao/groww-holdings.xlsx' => fn (string $path) => Fixtures::growwFunds($path, [
                ['Example ELSS Tax Saver Fund Direct Growth', 'INF000TEST21', 410.25, 55000, 52340.25],
                ['Example Long Term Equity Fund Direct Growth', 'INF000TEST22', 1320.5, 66000, 61875.40],
                ['Example Tax Advantage Fund Direct Growth', 'INF000TEST23', 96.125, 41000, 38214.10],
            ]),
            'Asha Rao/indmoney-us-stocks.xls' => [Fixtures::class, 'indmoneyUsStocks'],
        ];
    }

    private function processZip(array $entries): PortfolioFile
    {
        $zipPath = Storage::disk('portfolios')->path('uploads/clients.zip');
        @mkdir(dirname($zipPath), 0777, true);
        Fixtures::zip($zipPath, $entries);

        $parent = PortfolioFile::create([
            'user_id' => $this->user->id, 'original_name' => 'clients.zip', 'stored_name' => 'clients.zip', 'path' => 'uploads/clients.zip',
            'mime_type' => 'application/zip', 'file_size' => filesize($zipPath),
            'status' => PortfolioFile::STATUS_PENDING, 'meta' => ['extension' => 'zip'],
        ]);

        ProcessPortfolioFile::dispatchSync($parent);

        return $parent->fresh();
    }

    /** @return array{rows: string, score: string} */
    private function snapshot(string $client): array
    {
        $portfolio = Portfolio::where('name', $client)->sole();
        $riskScore = RiskScore::where('portfolio_id', $portfolio->id)->sole();
        $columns = ['name', 'asset_type', 'symbol', 'isin', 'quantity', 'buy_price', 'current_price', 'invested_value', 'current_value', 'profit_loss', 'risk_score', 'risk_level', 'meta'];

        return [
            'rows' => json_encode(PortfolioAsset::where('portfolio_id', $portfolio->id)->orderBy('id')->get()->map->only($columns)->all()),
            'score' => json_encode(['score' => $riskScore->score, 'volatility' => $riskScore->volatility, 'drawdown' => $riskScore->drawdown, 'meta' => $riskScore->meta]),
        ];
    }

    public function test_a_client_with_rupee_funds_and_us_stocks_is_valued_weighted_and_reported_on_converted_figures(): void
    {
        FxRate::create(['currency' => 'USD', 'rate' => 95.985, 'as_of' => '2026-09-29', 'source' => 'Test rate — invented', 'entered_by' => 'test', 'entered_at' => now()]);

        $parent = $this->processZip(self::dollarClientFiles() + self::rupeeClientFiles());

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $parent->status);
        $this->assertSame(2, $parent->meta['client_count']);

        // ── Asha Rao: 3 rupee funds + 16 US stocks ──
        $portfolio = Portfolio::where('name', 'Asha Rao')->sole();
        $assets = PortfolioAsset::where('portfolio_id', $portfolio->id)->get();
        $us = $assets->filter(fn ($a) => ($a->meta['currency'] ?? 'INR') === 'USD');
        $rupee = $assets->reject(fn ($a) => ($a->meta['currency'] ?? 'INR') === 'USD');

        $this->assertCount(19, $assets);
        $this->assertCount(3, $rupee);
        $this->assertCount(16, $us);

        $rupeeValue = round((float) $rupee->sum('current_value'), 2);
        $usValue = round((float) $us->sum('current_value'), 2);
        $usDollars = round($us->sum(fn ($a) => $a->meta['original']['current_value']), 2);

        $this->assertSame(152429.75, $rupeeValue);
        $this->assertSame(5515.04, $usDollars);
        $this->assertSame(529361.12, $usValue);                              // each holding × 95.985, rounded to paise
        $this->assertSame(['cost'], $us->map(fn ($a) => $a->meta['value_basis'])->unique()->values()->all());
        $this->assertSame('681790.87', $portfolio->total_value);
        $this->assertSame(77.64, round($usValue / (float) $portfolio->total_value * 100, 2));
        // Read as rupees, the same dollars would have been 3.49% of the portfolio.
        $this->assertSame(3.49, round($usDollars / ($rupeeValue + $usDollars) * 100, 2));

        // Gain/loss is measured over the three funds only: 1,62,000 paid, 1,52,429.75 now.
        $riskScore = RiskScore::where('portfolio_id', $portfolio->id)->sole();
        $this->assertSame('5.91', $riskScore->drawdown);
        $this->assertEquals(162000, $riskScore->meta['total_invested']);
        $this->assertEquals(681790.87, $riskScore->meta['total_current']);
        $this->assertSame('50.78', $riskScore->score);                    // 47.77 if the US stocks were counted as unmoved money
        $this->assertSame([null], $us->pluck('profit_loss')->unique()->values()->all());

        // Built from both files, and the report says how it was valued.
        $lead = PortfolioFile::where('portfolio_id', $portfolio->id)->whereNotNull('report_path')->sole();
        $this->assertSame(['included' => ['groww-holdings.xlsx', 'indmoney-us-stocks.xls'], 'skipped' => []], $lead->meta['client_sources']);

        $lines = ReportProvenance::for($assets, $lead->meta['client_sources']);
        $this->assertSame(['US-dollar holdings converted at ₹95.985 per USD (Test rate — invented, as of 29 Sep 2026).'], $lines['currency']);
        $this->assertNull($lines['staleness']);
        $this->assertSame('16 holdings are valued at cost — their source export has no current market price — and are excluded from the gain/loss figure.', $lines['valuation']);
        $this->assertSame('Built from 2 of 2 files.', $lines['sources']);

        // ── Vikram Rao: all rupees, one fund held through two brokers ──
        $other = Portfolio::where('name', 'Vikram Rao')->sole();
        $otherAssets = PortfolioAsset::where('portfolio_id', $other->id)->get();

        $this->assertCount(3, $otherAssets);
        $this->assertSame('200.0000', $otherAssets->firstWhere('isin', 'INF000TEST11')->quantity);
        $this->assertSame('96600.00', $other->total_value);
        $this->assertSame(93000.0, (float) $otherAssets->sum('invested_value'));
        $this->assertSame(json_decode(self::RUPEE_CLIENT_SCORE_BEFORE, true)['score'], RiskScore::where('portfolio_id', $other->id)->sole()->score);
    }

    public function test_an_all_rupee_client_folder_gives_the_same_rows_and_score_as_before_the_us_dollar_phase(): void
    {
        FxRate::create(['currency' => 'USD', 'rate' => 95.985, 'as_of' => '2026-09-29', 'source' => 'Test rate — invented', 'entered_by' => 'test', 'entered_at' => now()]);

        $this->processZip(self::rupeeClientFiles() + [
            'Second Client/a.csv' => "Scheme Name,ISIN,Units,Invested Value,Current Value\nExample Gilt Fund,,10,1000,1100\n",
        ]);

        $now = $this->snapshot('Vikram Rao');

        $this->assertSame(self::RUPEE_CLIENT_ROWS_BEFORE, $now['rows']);
        $this->assertSame(self::RUPEE_CLIENT_SCORE_BEFORE, $now['score']);
    }
}
