<?php

namespace App\Jobs;

use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Services\RiskEngine\AssetRiskScorer;
use App\Services\RiskEngine\PortfolioParser;
use App\Services\RiskEngine\PortfolioRiskCalculator;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessPortfolioFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | QUEUE SETTINGS
    |--------------------------------------------------------------------------
    */

    public int $timeout = 300;

    public int $tries = 3;

    public int $backoff = 60;

    public int $maxExceptions = 3;

    private const DISK = 'portfolios';

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        public readonly PortfolioFile $portfolioFile
    ) {}

    /*
    |--------------------------------------------------------------------------
    | HANDLE
    |--------------------------------------------------------------------------
    */

    public function handle(
        PortfolioParser          $parser,
        AssetRiskScorer          $assetScorer,
        PortfolioRiskCalculator  $calculator
    ): void {

        /*
        |----------------------------------------------------------------------
        | REFRESH MODEL
        |----------------------------------------------------------------------
        */

        $file = $this->portfolioFile->fresh();

        if (!$file) {
            Log::warning('ProcessPortfolioFile: file record deleted before processing.');
            return;
        }

        /*
        |----------------------------------------------------------------------
        | PREVENT DUPLICATE PROCESSING
        |----------------------------------------------------------------------
        */

        if ($file->status === PortfolioFile::STATUS_PROCESSED) {
            Log::info('ProcessPortfolioFile: already processed.', ['id' => $file->id]);
            return;
        }

        try {

            /*
            |------------------------------------------------------------------
            | MARK PROCESSING
            |------------------------------------------------------------------
            */

            $file->update([
                'status' => PortfolioFile::STATUS_PROCESSING,
                'meta'   => array_merge($file->meta ?? [], [
                    'processing_started_at' => now()->toIso8601String(),
                    'attempt'               => $this->attempts(),
                ]),
            ]);

            Log::info('ProcessPortfolioFile: started.', [
                'id'       => $file->id,
                'user_id'  => $file->user_id,
                'filename' => $file->original_name,
            ]);

            /*
            |------------------------------------------------------------------
            | VERIFY PHYSICAL FILE EXISTS
            |------------------------------------------------------------------
            */

            if (!Storage::disk(self::DISK)->exists($file->path)) {
                throw new \RuntimeException('Portfolio file missing from storage: ' . $file->path);
            }

            $extension = strtolower(pathinfo($file->path, PATHINFO_EXTENSION));

            /*
            |------------------------------------------------------------------
            | PARSE HOLDINGS
            |------------------------------------------------------------------
            */

            $parseResult = $parser->parse($file);
            $holdings    = $parseResult['rows'];
            $parseErrors = $parseResult['errors'];

            Log::info('ProcessPortfolioFile: parsed.', [
                'id'          => $file->id,
                'rows_found'  => count($holdings),
                'parse_errors'=> $parseErrors,
            ]);

            /*
            |------------------------------------------------------------------
            | PERSIST ASSETS (inside a transaction)
            |------------------------------------------------------------------
            */

            $portfolioId = $file->portfolio_id;

            DB::transaction(function () use (
                $file, $holdings, $portfolioId, $assetScorer, $calculator
            ) {

                /*
                |--------------------------------------------------------------
                | DELETE OLD ASSETS FOR THIS FILE'S PORTFOLIO (re-process safe)
                |--------------------------------------------------------------
                */

                if ($portfolioId) {
                    PortfolioAsset::where('portfolio_id', $portfolioId)->delete();
                }

                $assetModels = collect();

                foreach ($holdings as $row) {

                    // Score each asset individually
                    $assetScore = $assetScorer->score(
                        $row['asset_type'],
                        $row['name']
                    );

                    $asset = PortfolioAsset::create([
                        'portfolio_id'   => $portfolioId,
                        'asset_type'     => $row['asset_type'],
                        'symbol'         => $row['symbol'],
                        'name'           => $row['name'],
                        'isin'           => $row['isin'],
                        'quantity'       => $row['quantity'],
                        'buy_price'      => $row['buy_price'],
                        'current_price'  => $row['current_price'],
                        'invested_value' => $row['invested_value'],
                        'current_value'  => $row['current_value'],
                        'profit_loss'    => $row['profit_loss'],
                        'risk_score'     => $assetScore,
                        'risk_level'     => $assetScorer->level($assetScore),
                        'meta'           => ['source_file_id' => $file->id],
                    ]);

                    $assetModels->push($asset);
                }

                /*
                |--------------------------------------------------------------
                | RECALCULATE PORTFOLIO TOTALS
                |--------------------------------------------------------------
                */

                if ($portfolioId && $assetModels->isNotEmpty()) {

                    /** @var Portfolio $portfolio */
                    $portfolio = Portfolio::find($portfolioId);

                    if ($portfolio) {
                        $portfolio->recalculateMetrics();
                    }
                }

                /*
                |--------------------------------------------------------------
                | GENERATE IMMEDIATE RISK SCORE SNAPSHOT
                |--------------------------------------------------------------
                */

                if ($assetModels->isNotEmpty()) {

                    $result = $calculator->calculate($assetModels);

                    RiskScore::create([
                        'user_id'      => $file->user_id,
                        'portfolio_id' => $portfolioId,
                        'score'        => $result['score'],
                        'volatility'   => $result['volatility'],
                        'drawdown'     => $result['drawdown'],
                        'generated_at' => now(),
                        'meta'         => array_merge($result['meta'], [
                            'trigger'     => 'file_upload',
                            'next_action' => $result['next_action'],
                            'risk_flags'  => $result['risk_flags'],
                        ]),
                    ]);

                    Log::info('ProcessPortfolioFile: risk score saved.', [
                        'id'          => $file->id,
                        'score'       => $result['score'],
                        'risk_level'  => $result['meta']['risk_level'],
                        'asset_count' => count($assetModels),
                    ]);
                }
            });

            /*
            |------------------------------------------------------------------
            | MARK PROCESSED
            |------------------------------------------------------------------
            */

            $file->update([
                'status'       => PortfolioFile::STATUS_PROCESSED,
                'processed_at' => now(),
                'meta'         => array_merge($file->meta ?? [], [
                    'processing_completed_at' => now()->toIso8601String(),
                    'extension'               => $extension,
                    'holdings_parsed'         => count($holdings),
                    'parse_errors'            => $parseErrors,
                ]),
            ]);

            Log::info('ProcessPortfolioFile: completed.', [
                'id'             => $file->id,
                'holdings_saved' => count($holdings),
            ]);

        } catch (\Throwable $e) {

            /*
            |------------------------------------------------------------------
            | MARK FAILED
            |------------------------------------------------------------------
            */

            $file->update([
                'status' => PortfolioFile::STATUS_FAILED,
                'meta'   => array_merge($file->meta ?? [], [
                    'failed_at'     => now()->toIso8601String(),
                    'error_message' => $e->getMessage(),
                ]),
            ]);

            Log::error('ProcessPortfolioFile: failed.', [
                'id'      => $file->id,
                'user_id' => $file->user_id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
