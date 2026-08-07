<?php

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\User;
use App\Services\RiskEngine\AssetRiskScorer;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessPortfolioFileTest extends TestCase
{
    use RefreshDatabase;

    private function makePortfolioFile(string $csv): PortfolioFile
    {
        Storage::fake('portfolios');
        Mail::fake();

        $user = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Test Portfolio']);

        Storage::disk('portfolios')->put('uploads/portfolio.csv', $csv);

        return PortfolioFile::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio->id,
            'original_name' => 'portfolio.csv',
            'stored_name' => 'portfolio.csv',
            'path' => 'uploads/portfolio.csv',
            'mime_type' => 'text/csv',
            'file_size' => strlen($csv),
            'status' => PortfolioFile::STATUS_PENDING,
        ]);
    }

    public function test_classify_batch_is_called_once_with_deduped_symbols_before_the_transaction_opens(): void
    {
        $csv = implode("\n", [
            'name,asset_type,symbol,current_value',
            'Reliance Industries,stock,RELIANCE,25000',
            'Reliance Industries Again,stock,RELIANCE,15000',
            'Govt Bond,bond,,10000',
        ]);

        $file = $this->makePortfolioFile($csv);

        // RefreshDatabase wraps every test in its own outer transaction, so the
        // baseline level here isn't necessarily 0 — what matters is that the
        // job's own DB::transaction() (ProcessPortfolioFile.php) hasn't been
        // entered yet when classifyBatch() runs, i.e. the level hasn't grown.
        $baselineTransactionLevel = DB::transactionLevel();
        $capturedTransactionLevel = null;

        $this->mock(StockRiskService::class)
            ->shouldReceive('classifyBatch')
            ->once()
            ->with(['RELIANCE'])
            ->andReturnUsing(function () use (&$capturedTransactionLevel) {
                $capturedTransactionLevel = DB::transactionLevel();

                return [
                    'RELIANCE' => [
                        'risk_level' => 'Medium',
                        'volatility' => 30.0,
                        'confidence' => 0.8,
                        'as_of_date' => '2026-07-01',
                        'stale' => false,
                        'unavailable' => false,
                    ],
                ];
            });

        ProcessPortfolioFile::dispatchSync($file);

        // Proves the call happened outside DB::transaction() — not just that
        // it's textually positioned before it in the source.
        expect($capturedTransactionLevel)->toBe($baselineTransactionLevel);

        $reliance1 = PortfolioAsset::where('name', 'Reliance Industries')->first();
        $reliance2 = PortfolioAsset::where('name', 'Reliance Industries Again')->first();
        $bond = PortfolioAsset::where('name', 'Govt Bond')->first();

        expect((float) $reliance1->risk_score)->toBe(65.0)
            ->and($reliance1->meta['stock_risk']['source'])->toBe(AssetRiskScorer::SOURCE_LIVE)
            ->and($reliance1->meta['stock_risk']['risk_level'])->toBe('Medium')
            ->and((float) $reliance2->risk_score)->toBe(65.0)
            ->and($reliance2->meta['stock_risk']['source'])->toBe(AssetRiskScorer::SOURCE_LIVE);

        // Fixed-income rows never get a stock_risk key at all.
        expect((float) $bond->risk_score)->toBe(15.0)
            ->and($bond->meta)->not->toHaveKey('stock_risk');

        // Bypass Eloquent's model/cast layer entirely — raw query builder,
        // fresh connection, manual json_decode of the actual stored column
        // bytes. Proves the row in SQLite itself carries stock_risk, not
        // just the in-memory $asset object handed back from create().
        $rawRow = DB::table('portfolio_assets')
            ->where('name', 'Reliance Industries')
            ->value('meta');

        $rawMeta = json_decode($rawRow, true);

        // json_encode(30.0) writes the bytes "30" (no decimal point), so
        // json_decode reads it back as int, not float — a real artifact of
        // the round trip through the meta column, not something you'd see
        // from an in-memory object that never touched the database.
        expect($rawMeta['stock_risk']['source'])->toBe('live')
            ->and($rawMeta['stock_risk']['risk_level'])->toBe('Medium')
            ->and((float) $rawMeta['stock_risk']['volatility'])->toBe(30.0);
    }

    public function test_stock_with_empty_symbol_falls_back_without_being_sent_to_classify_batch(): void
    {
        $csv = implode("\n", [
            'name,asset_type,symbol,current_value',
            'Mystery Stock,stock,,20000',
            'Reliance Industries,stock,RELIANCE,25000',
        ]);

        $file = $this->makePortfolioFile($csv);

        $this->mock(StockRiskService::class)
            ->shouldReceive('classifyBatch')
            ->once()
            ->with(['RELIANCE']) // the empty-symbol row must never reach this call
            ->andReturn([
                'RELIANCE' => [
                    'risk_level' => 'High', 'volatility' => 50.0, 'confidence' => 0.9,
                    'as_of_date' => '2026-07-01', 'stale' => false, 'unavailable' => false,
                ],
            ]);

        ProcessPortfolioFile::dispatchSync($file);

        $mystery = PortfolioAsset::where('name', 'Mystery Stock')->first();

        expect((float) $mystery->risk_score)->toBe(65.0)
            ->and($mystery->meta['stock_risk']['source'])->toBe(AssetRiskScorer::SOURCE_FALLBACK_UNAVAILABLE)
            ->and($mystery->meta['stock_risk']['risk_level'])->toBeNull();
    }

    public function test_stock_with_unavailable_classification_falls_back_to_65(): void
    {
        $csv = implode("\n", [
            'name,asset_type,symbol,current_value',
            'Zomato,stock,ZOMATO,20000',
        ]);

        $file = $this->makePortfolioFile($csv);

        $this->mock(StockRiskService::class)
            ->shouldReceive('classifyBatch')
            ->once()
            ->with(['ZOMATO'])
            ->andReturn([
                'ZOMATO' => [
                    'risk_level' => null, 'volatility' => null, 'confidence' => null,
                    'as_of_date' => null, 'stale' => false, 'unavailable' => true,
                ],
            ]);

        ProcessPortfolioFile::dispatchSync($file);

        $asset = PortfolioAsset::where('name', 'Zomato')->first();

        expect((float) $asset->risk_score)->toBe(65.0)
            ->and($asset->meta['stock_risk']['source'])->toBe(AssetRiskScorer::SOURCE_FALLBACK_UNAVAILABLE);
    }

    // ─── distinct-stock-symbol cap ────────────────────────────────────────

    private function csvWithDistinctStockSymbols(int $count): string
    {
        $rows = ['name,asset_type,symbol,current_value'];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = "Stock {$i},stock,SYM{$i},1000";
        }

        return implode("\n", $rows);
    }

    public function test_portfolio_with_too_many_distinct_stock_symbols_is_rejected_before_classify_batch(): void
    {
        $file = $this->makePortfolioFile($this->csvWithDistinctStockSymbols(501));

        // A spy, not a stub: proves the expensive downstream call never fires,
        // not just that the end state happens to look right.
        $this->mock(StockRiskService::class)
            ->shouldReceive('classifyBatch')
            ->never();

        ProcessPortfolioFile::dispatchSync($file);

        $file->refresh();

        expect($file->status)->toBe(PortfolioFile::STATUS_FAILED)
            ->and($file->meta['error_message'])->toContain('too many distinct stock symbols')
            ->and($file->meta['error_message'])->toContain('501')
            ->and($file->meta['error_message'])->toContain('500');

        // Rejection happens before the transaction that would create assets.
        expect(PortfolioAsset::count())->toBe(0);
    }

    public function test_portfolio_with_distinct_stock_symbols_under_the_cap_still_processes_end_to_end(): void
    {
        $symbolCount = 50;
        $file = $this->makePortfolioFile($this->csvWithDistinctStockSymbols($symbolCount));

        $expectedSymbols = collect(range(1, $symbolCount))->map(fn ($i) => "SYM{$i}")->all();

        $this->mock(StockRiskService::class)
            ->shouldReceive('classifyBatch')
            ->once()
            ->with($expectedSymbols)
            ->andReturn(collect($expectedSymbols)->mapWithKeys(fn ($symbol) => [
                $symbol => [
                    'risk_level' => 'Medium', 'volatility' => 30.0, 'confidence' => 0.8,
                    'as_of_date' => '2026-07-01', 'stale' => false, 'unavailable' => false,
                ],
            ])->all());

        ProcessPortfolioFile::dispatchSync($file);

        $file->refresh();

        expect($file->status)->toBe(PortfolioFile::STATUS_PROCESSED)
            ->and(PortfolioAsset::count())->toBe($symbolCount);

        $first = PortfolioAsset::where('symbol', 'SYM1')->first();
        expect((float) $first->risk_score)->toBe(65.0)
            ->and($first->meta['stock_risk']['source'])->toBe(AssetRiskScorer::SOURCE_LIVE);
    }
}
