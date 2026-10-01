<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\User;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BrokerExportFixtures;
use Tests\TestCase;

/**
 * End to end through ProcessPortfolioFile: real-export shapes are stored with
 * correct values, and an unknown cost basis is persisted as NULL — never as a
 * 0 that would read as +100% profit.
 */
class BrokerExportImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(string $filename, callable $write): PortfolioFile
    {
        Storage::fake('portfolios');
        Mail::fake();

        $this->mock(StockRiskService::class)
            ->shouldReceive('classifyBatch')
            ->andReturn([]);

        $user = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

        $path = Storage::disk('portfolios')->path('uploads/'.$filename);
        @mkdir(dirname($path), 0777, true);
        $write($path);

        $file = PortfolioFile::create([
            'user_id' => $user->id,
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

    public function test_a_groww_export_is_processed_with_its_values_and_cost_basis(): void
    {
        $file = $this->upload('groww.xlsx', [BrokerExportFixtures::class, 'growwMutualFundHoldings']);

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $file->status);

        $assets = PortfolioAsset::orderBy('id')->get();

        $this->assertCount(3, $assets);
        $this->assertSame(['50000.00', '60000.00', '40000.00'], $assets->pluck('invested_value')->all());
        $this->assertSame(['55000.00', '64000.00', '43500.00'], $assets->pluck('current_value')->all());
        $this->assertSame(['file', 'file', 'file'], $assets->pluck('meta.invested_value_source')->all());
    }

    public function test_an_unknown_cost_basis_is_stored_as_null_not_as_zero_profit(): void
    {
        $file = $this->upload('no-cost.csv', fn (string $path) => file_put_contents(
            $path,
            "name,asset_type,current_value\nExample Gilt Fund,bond,104000\n",
        ));

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $file->status);

        $asset = PortfolioAsset::sole();

        $this->assertNull($asset->invested_value);
        $this->assertNull($asset->profit_loss);
        $this->assertSame('unknown', $asset->meta['invested_value_source']);
    }

    public function test_a_us_dollar_export_fails_with_the_reason_shown_to_the_advisor_when_no_exchange_rate_is_set(): void
    {
        $file = $this->upload('indmoney.xls', [BrokerExportFixtures::class, 'indmoneyUsStocks']);

        $this->assertSame(PortfolioFile::STATUS_FAILED, $file->status);
        $this->assertSame("US-dollar holdings can't be valued yet: no exchange rate is set.", $file->meta['error_message']);
        $this->assertSame(0, PortfolioAsset::count());
    }
}
