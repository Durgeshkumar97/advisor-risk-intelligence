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
use App\Services\RiskEngine\PortfolioParser;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BrokerExportFixtures as Fixtures;
use Tests\TestCase;

/**
 * US-dollar holdings, end to end through ProcessPortfolioFile: converted to
 * rupees at the operator's rate, stored with the rate and the dollar amounts,
 * and never mixed unconverted with rupee holdings.
 *
 * The US fixture (Fixtures::indmoneyUsStocks) is 16 invented tickers costing
 * $5,515.04 in total; it has no market values, so it is valued at cost.
 */
class UsdHoldingsTest extends TestCase
{
    use RefreshDatabase;

    private const RATE_SOURCE = 'Test rate — invented';

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

    private function setRate(string $asOf = '2026-09-29', float $rate = 95.985): void
    {
        FxRate::create(['currency' => 'USD', 'rate' => $rate, 'as_of' => $asOf, 'source' => self::RATE_SOURCE, 'entered_by' => 'test', 'entered_at' => now()]);
    }

    private function upload(string $filename, callable $write): PortfolioFile
    {
        $portfolio = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Client A']);

        $path = Storage::disk('portfolios')->path('uploads/'.$filename);
        @mkdir(dirname($path), 0777, true);
        $write($path);

        $file = PortfolioFile::create([
            'user_id' => $this->user->id,
            'portfolio_id' => $portfolio->id,
            'original_name' => $filename,
            'stored_name' => $filename,
            'path' => 'uploads/'.$filename,
            'mime_type' => 'application/octet-stream',
            'file_size' => filesize($path),
            'status' => PortfolioFile::STATUS_PENDING,
        ]);

        ProcessPortfolioFile::dispatchSync($file);

        return $file->fresh();
    }

    private function processZip(array $entries): PortfolioFile
    {
        $zipPath = Storage::disk('portfolios')->path('uploads/clients.zip');
        @mkdir(dirname($zipPath), 0777, true);
        Fixtures::zip($zipPath, $entries);

        $parent = PortfolioFile::create([
            'user_id' => $this->user->id,
            'original_name' => 'clients.zip',
            'stored_name' => 'clients.zip',
            'path' => 'uploads/clients.zip',
            'mime_type' => 'application/zip',
            'file_size' => filesize($zipPath),
            'status' => PortfolioFile::STATUS_PENDING,
            'meta' => ['extension' => 'zip'],
        ]);

        ProcessPortfolioFile::dispatchSync($parent);

        return $parent->fresh();
    }

    // ─── 1. a dollar file alone ──────────────────────────────────────────

    public function test_a_us_dollar_file_is_stored_in_rupees_with_the_rate_and_the_dollar_amounts(): void
    {
        $this->setRate();

        $file = $this->upload('us-stocks.xls', [Fixtures::class, 'indmoneyUsStocks']);

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $file->status);

        $assets = PortfolioAsset::orderBy('id')->get();
        $this->assertCount(16, $assets);

        // Row 9 of the export: 0.25 shares at an average of $10.
        $first = $assets[0];
        $this->assertSame('TSTA', $first->name);
        $this->assertSame('foreign_stock', $first->asset_type);
        $this->assertSame('239.96', $first->current_value);       // $2.50 × 95.985
        $this->assertSame('239.96', $first->invested_value);
        $this->assertSame('959.85', $first->buy_price);
        $this->assertNull($first->profit_loss);
        $this->assertSame('USD', $first->meta['currency']);
        $this->assertSame('cost', $first->meta['value_basis']);
        $this->assertSame(95.985, $first->meta['fx_rate']);
        $this->assertSame('2026-09-29', $first->meta['fx_as_of']);
        $this->assertSame(self::RATE_SOURCE, $first->meta['fx_source']);
        $this->assertSame(['current_value' => 2.5, 'invested_value' => 2.5, 'buy_price' => 10, 'current_price' => 0], $first->meta['original']);

        // The whole portfolio is the rupee value of $5,515.04, not 5,515.04.
        $this->assertSame('529361.12', Portfolio::sole()->total_value);
        $this->assertSame(5515.04, round($assets->sum(fn ($a) => $a->meta['original']['current_value']), 2));

        foreach ($assets as $asset) {
            $this->assertSame(round($asset->meta['original']['current_value'] * 95.985, 2), (float) $asset->current_value);
        }
    }

    // ─── 2. rupee and dollar files of one client ─────────────────────────

    public function test_a_client_with_a_rupee_file_and_a_dollar_file_gets_one_portfolio_weighted_on_converted_values(): void
    {
        $this->setRate();

        $this->processZip([
            'Rajesh Kumar/groww.xlsx' => [Fixtures::class, 'growwMutualFundHoldings'],      // ₹1,62,500 in 3 funds
            'Rajesh Kumar/us-stocks.xls' => [Fixtures::class, 'indmoneyUsStocks'],          // $5,515.04 in 16 stocks
            'Priya Sharma/holdings.csv' => "Scheme Name,ISIN,Units,Invested Value,Current Value\nExample Gilt Fund,,10,1000,1100\n",
        ]);

        $portfolio = Portfolio::where('name', 'Rajesh Kumar')->sole();
        $assets = PortfolioAsset::where('portfolio_id', $portfolio->id)->get();
        $us = $assets->filter(fn ($a) => ($a->meta['currency'] ?? 'INR') === 'USD');

        $this->assertCount(19, $assets);
        $this->assertCount(16, $us);
        $this->assertSame(1, RiskScore::where('portfolio_id', $portfolio->id)->count());

        $usValue = round((float) $us->sum('current_value'), 2);
        $total = (float) $portfolio->total_value;

        $this->assertSame(529361.12, $usValue);
        $this->assertSame(691861.12, $total);                               // 1,62,500 + 5,29,361.12
        $this->assertSame(76.51, round($usValue / $total * 100, 2));        // read as rupees it would be 3.28%

        $lead = PortfolioFile::where('portfolio_id', $portfolio->id)->whereNotNull('report_path')->sole();
        $this->assertSame(['groww.xlsx', 'us-stocks.xls'], $lead->meta['client_sources']['included']);
        $this->assertSame([], $lead->meta['client_sources']['skipped']);
        $this->assertSame([sprintf(PortfolioParser::VALUED_AT_COST_WARNING, 16, 's are', 'they are')], $lead->meta['parse_warnings']);
    }

    public function test_the_same_us_stock_in_two_dollar_files_merges_with_its_gain_or_loss_still_unknown(): void
    {
        $this->setRate();

        $this->processZip([
            'Rajesh Kumar/us-a.csv' => "Stock Symbol,Quantity,Avg. Price ($)\nTSTA,2,100\n",
            'Rajesh Kumar/us-b.csv' => "Stock Symbol,Quantity,Avg. Price ($)\nTSTA,3,120\n",
            'Priya Sharma/holdings.csv' => "Scheme Name,ISIN,Units,Invested Value,Current Value\nExample Gilt Fund,,10,1000,1100\n",
        ]);

        $asset = PortfolioAsset::where('name', 'TSTA')->sole();

        $this->assertSame('5.0000', $asset->quantity);
        $this->assertSame('53751.60', $asset->current_value);      // (200 + 360) × 95.985
        $this->assertSame('53751.60', $asset->invested_value);
        $this->assertNull($asset->profit_loss);                    // not 0
        $this->assertSame('0.00', $asset->current_price);
        $this->assertSame('cost', $asset->meta['value_basis']);
        $this->assertEquals(560.0, $asset->meta['original']['current_value']);
        $this->assertEquals(560.0, $asset->meta['original']['invested_value']);
    }

    // ─── 3–4. no rate, an expired rate, a stale rate ─────────────────────

    public function test_with_no_rate_a_dollar_file_in_a_folder_is_skipped_and_nothing_of_it_is_stored(): void
    {
        $this->processZip([
            'Rajesh Kumar/groww.xlsx' => [Fixtures::class, 'growwMutualFundHoldings'],
            'Rajesh Kumar/us-stocks.xls' => [Fixtures::class, 'indmoneyUsStocks'],
            'Priya Sharma/holdings.csv' => "Scheme Name,ISIN,Units,Invested Value,Current Value\nExample Gilt Fund,,10,1000,1100\n",
        ]);

        $portfolio = Portfolio::where('name', 'Rajesh Kumar')->sole();

        $this->assertSame(3, PortfolioAsset::where('portfolio_id', $portfolio->id)->count());
        $this->assertSame('162500.00', $portfolio->total_value);            // no dollar figure added in
        $this->assertSame(
            'Scored from 1 of 2 files; skipped: us-stocks.xls — '.PortfolioParser::NO_FX_RATE_MESSAGE,
            PortfolioFile::where('original_name', 'groww.xlsx')->sole()->meta['parse_warnings'][0],
        );
    }

    public function test_a_rate_32_days_old_is_refused_and_one_8_days_old_is_used_with_a_warning(): void
    {
        $this->setRate('2026-08-30');     // 32 days before 1 Oct

        $expired = $this->upload('expired.xls', [Fixtures::class, 'indmoneyUsStocks']);

        $this->assertSame(PortfolioFile::STATUS_FAILED, $expired->status);
        $this->assertSame("US-dollar holdings can't be valued: the exchange rate on file is dated 30 Aug 2026, which is more than 31 days old.", $expired->meta['error_message']);
        $this->assertSame(0, PortfolioAsset::count());

        $this->setRate('2026-09-23');     // 8 days

        $stale = $this->upload('stale.xls', [Fixtures::class, 'indmoneyUsStocks']);

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $stale->status);
        $this->assertSame(16, PortfolioAsset::count());
        $this->assertContains(
            'US-dollar holdings were converted at an exchange rate dated 23 Sep 2026, which is more than 7 days old.',
            $stale->meta['parse_warnings'],
        );
    }

    // ─── currencies are never mixed ──────────────────────────────────────

    public function test_a_dollar_holding_that_reaches_the_job_unconverted_is_refused_and_nothing_is_stored(): void
    {
        // A parser that hands back a dollar row with no conversion on record —
        // the bug this phase exists to prevent — next to a rupee row.
        $this->mock(PortfolioParser::class)->shouldReceive('parse')->andReturn([
            'rows' => [
                ['name' => 'Example Fund', 'asset_type' => 'mutual_fund', 'symbol' => null, 'isin' => null, 'quantity' => 1.0, 'buy_price' => 100.0, 'current_price' => 110.0, 'invested_value' => 100.0, 'current_value' => 110.0, 'profit_loss' => 10.0, 'invested_value_source' => 'file'],
                ['name' => 'TSTA', 'asset_type' => 'foreign_stock', 'symbol' => null, 'isin' => null, 'quantity' => 1.0, 'buy_price' => 100.0, 'current_price' => 0.0, 'invested_value' => 100.0, 'current_value' => 100.0, 'profit_loss' => null, 'invested_value_source' => 'derived', 'currency' => 'USD', 'value_basis' => 'cost'],
            ],
            'errors' => [], 'warnings' => [], 'count' => 2,
        ]);

        try {
            $this->upload('mixed.csv', fn (string $path) => file_put_contents($path, "name,current_value\nx,1\n"));
            $this->fail('An unconverted US-dollar holding was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('without being converted to rupees', $e->getMessage());
        }

        $this->assertSame(0, PortfolioAsset::count());
        $this->assertSame(0, RiskScore::count());
        $this->assertSame(PortfolioFile::STATUS_FAILED, PortfolioFile::sole()->status);
    }
}
