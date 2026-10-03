<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BrokerExportFixtures as Fixtures;
use Tests\Support\FixedFxRate;
use Tests\TestCase;

/**
 * Regression guard for the US-dollar phase: an upload that is entirely in
 * rupees must produce exactly the rows, score and report it produced before
 * that phase existed — whether or not an exchange rate is on file.
 *
 * The expected values below were recorded from the code as it stood before
 * the phase (main at 6a40e82), not derived from the current code.
 */
class RupeeRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const ROWS_BEFORE = '[{"name":"Example Bluechip Fund Direct Growth","asset_type":"mutual_fund","symbol":null,"isin":"INF000TEST01","quantity":"1234.5670","buy_price":"40.50","current_price":"44.55","invested_value":"50000.00","current_value":"55000.00","profit_loss":"5000.00","risk_score":"40.00","risk_level":"MEDIUM","meta":{"source_file_id":1,"invested_value_source":"file"}},{"name":"Example Midcap Opportunities Fund Direct Growth","asset_type":"mutual_fund","symbol":null,"isin":"INF000TEST02","quantity":"812.3450","buy_price":"73.86","current_price":"78.78","invested_value":"60000.00","current_value":"64000.00","profit_loss":"4000.00","risk_score":"45.00","risk_level":"MEDIUM","meta":{"source_file_id":1,"invested_value_source":"file"}},{"name":"Example Liquid Fund Direct Growth","asset_type":"mutual_fund","symbol":null,"isin":"INF000TEST03","quantity":"12.5000","buy_price":"3200.00","current_price":"3480.00","invested_value":"40000.00","current_value":"43500.00","profit_loss":"3500.00","risk_score":"17.00","risk_level":"LOW","meta":{"source_file_id":1,"invested_value_source":"file"}}]';

    private const SCORE_BEFORE = '{"score":"30.82","volatility":"14.93","drawdown":"0.00","meta":{"composition_score":35.81,"concentration_score":1.2,"equity_ratio_score":73.23,"drawdown_score":0,"equity_ratio_pct":73.2,"hhi":0.3413,"asset_count":3,"dominant_asset_type":"mutual_fund","total_invested":150000,"total_current":162500,"market_multiplier":1.05,"risk_flags":["LOW_DIVERSIFICATION"],"risk_level":"MEDIUM","stock_risk_fallback_count":0,"calculator_version":"portfolio-risk-calculator-v1","trigger":"file_upload","next_action":"Overall risk sits within acceptable risk parameters."}}';

    /**
     * The rendered report. Unlike the rows and score above, this is NOT the
     * pre-US-dollar value any more: it is re-recorded whenever the report
     * template is changed on purpose. History:
     *   f030a6fa…25a4  main at 6a40e82, before the US-dollar phase
     *   62dbe31d…e894  page one: "Largest holding" and "Gain / loss vs cost" tiles
     *   55969888…ee40  risk flags printed as sentences
     *   ad248915…87ae  asset types printed as labels
     *   c31fb733…32ec  each score says where it came from, plus the "Risk scores:" line
     */
    private const REPORT_SHA256 = 'c31fb73309d330168f5997f7e3bb797c092d49feeb6badef29f8c2dc8be632ec';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 10:00:00');
        Storage::fake('portfolios');
        Mail::fake();
        config(['app.url' => 'https://risksignal.test']);

        $this->mock(StockRiskService::class)->shouldReceive('classifyBatch')->andReturn([]);
    }

    /** @return array{rows: string, score: string, report: string} */
    private function uploadGrowwExport(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

        $path = Storage::disk('portfolios')->path('uploads/groww.xlsx');
        @mkdir(dirname($path), 0777, true);
        Fixtures::growwMutualFundHoldings($path);

        $file = PortfolioFile::create([
            'user_id' => $user->id, 'portfolio_id' => $portfolio->id,
            'original_name' => 'groww.xlsx', 'stored_name' => 'groww.xlsx', 'path' => 'uploads/groww.xlsx',
            'mime_type' => 'application/octet-stream', 'file_size' => filesize($path),
            'status' => PortfolioFile::STATUS_PENDING,
        ]);

        ProcessPortfolioFile::dispatchSync($file);

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $file->fresh()->status);

        $columns = ['name', 'asset_type', 'symbol', 'isin', 'quantity', 'buy_price', 'current_price', 'invested_value', 'current_value', 'profit_loss', 'risk_score', 'risk_level', 'meta'];
        $assets = PortfolioAsset::where('portfolio_id', $portfolio->id)->orderByDesc('risk_score')->get();
        $riskScore = RiskScore::where('portfolio_id', $portfolio->id)->sole();

        // The view exactly as the job renders it into the PDF.
        $html = view('reports.risk-report', [
            'portfolio' => $portfolio->fresh(), 'riskScore' => $riskScore, 'assets' => $assets, 'file' => $file->fresh(),
        ] + (isset($file->fresh()->meta['client_sources']) ? ['clientSources' => $file->fresh()->meta['client_sources']] : []))->render();

        return [
            'rows' => json_encode(PortfolioAsset::where('portfolio_id', $portfolio->id)->orderBy('id')->get()->map->only($columns)->all()),
            'score' => json_encode(['score' => $riskScore->score, 'volatility' => $riskScore->volatility, 'drawdown' => $riskScore->drawdown, 'meta' => $riskScore->meta]),
            // Whitespace between tags carries no meaning in the PDF.
            'report' => hash('sha256', trim(preg_replace('/\s+/', ' ', $html))),
        ];
    }

    public function test_an_all_rupee_upload_gives_the_same_rows_score_and_report_as_before_the_us_dollar_phase(): void
    {
        $result = $this->uploadGrowwExport();

        $this->assertSame(self::ROWS_BEFORE, $result['rows']);
        $this->assertSame(self::SCORE_BEFORE, $result['score']);
        $this->assertSame(self::REPORT_SHA256, $result['report'], 'The rendered report of an all-rupee upload changed.');
    }

    public function test_having_an_exchange_rate_on_file_changes_nothing_for_an_all_rupee_upload(): void
    {
        app()->instance(\App\Contracts\FxRateProvider::class, new FixedFxRate);

        $result = $this->uploadGrowwExport();

        $this->assertSame(self::ROWS_BEFORE, $result['rows']);
        $this->assertSame(self::SCORE_BEFORE, $result['score']);
        $this->assertSame(self::REPORT_SHA256, $result['report']);
    }
}
