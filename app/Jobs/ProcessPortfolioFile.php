<?php

namespace App\Jobs;

use App\Mail\RiskReportMail;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Services\RiskEngine\AssetRiskScorer;
use App\Services\RiskEngine\PortfolioParser;
use App\Services\RiskEngine\PortfolioRiskCalculator;
use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessPortfolioFile implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout       = 300;
    public int $tries         = 3;
    public int $backoff       = 60;
    public int $maxExceptions = 3;

    private const DISK               = 'portfolios';
    private const ALLOWED_EXTENSIONS = ['csv', 'xlsx', 'xls', 'pdf'];
    private const MIME_MAP           = [
        'csv'  => 'text/csv',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
        'pdf'  => 'application/pdf',
    ];
    private const GENERIC_BASENAMES  = [
        'book1', 'untitled', 'sheet1', 'empty', 'portfolio',
        'data', 'export', 'file', 'document', 'upload',
    ];

    public function __construct(
        public readonly PortfolioFile $portfolioFile
    ) {}

    public function handle(
        PortfolioParser         $parser,
        AssetRiskScorer         $assetScorer,
        PortfolioRiskCalculator $calculator
    ): void {
        $file = $this->portfolioFile->fresh();

        if (!$file) {
            Log::warning('ProcessPortfolioFile: file record deleted before processing.');
            return;
        }

        if ($file->status === PortfolioFile::STATUS_PROCESSED) {
            Log::info('ProcessPortfolioFile: already processed.', ['id' => $file->id]);
            return;
        }

        try {
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


            if (!Storage::disk(self::DISK)->exists($file->path)) {
                throw new \RuntimeException('Portfolio file missing from storage: ' . $file->path);
            }

            $extension    = strtolower(pathinfo($file->path, PATHINFO_EXTENSION));
            $absolutePath = Storage::disk(self::DISK)->path($file->path);

            if ($extension === 'zip') {
                $this->handleZipExtraction($file, $absolutePath);
                return;
            }

            $parseResult = $parser->parse($file);
            $holdings    = $parseResult['rows'];
            $parseErrors = $parseResult['errors'];

            Log::info('ProcessPortfolioFile: parsed.', [
                'id'           => $file->id,
                'rows_found'   => count($holdings),
                'parse_errors' => $parseErrors,
            ]);

            $portfolioId = $file->portfolio_id;
            $riskScore   = null;
            $reportPath  = null;

            DB::transaction(function () use (
                $file, $holdings, $portfolioId, $assetScorer, $calculator, $extension, $parseErrors,
                &$riskScore, &$reportPath
            ) {
                if ($portfolioId) {
                    PortfolioAsset::where('portfolio_id', $portfolioId)->delete();
                }

                $assetModels = collect();

                foreach ($holdings as $row) {
                    $assetScore = $assetScorer->score($row['asset_type'], $row['name']);

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

                if ($portfolioId && $assetModels->isNotEmpty()) {
                    $portfolio = Portfolio::find($portfolioId);
                    if ($portfolio) {
                        $portfolio->recalculateMetrics();
                    }
                }

                if ($assetModels->isNotEmpty()) {
                    $result = $calculator->calculate($assetModels);

                    $riskScore = RiskScore::create([
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

                // PDF inside transaction — render failure rolls back RiskScore + assets
                $reportPath = $riskScore
                    ? $this->generatePdfReport($file, $riskScore, $portfolioId)
                    : null;

                $file->update([
                    'status'       => PortfolioFile::STATUS_PROCESSED,
                    'processed_at' => now(),
                    'report_path'  => $reportPath,
                    'meta'         => array_merge($file->meta ?? [], [
                        'processing_completed_at' => now()->toIso8601String(),
                        'extension'               => $extension,
                        'holdings_parsed'         => count($holdings),
                        'parse_errors'            => $parseErrors,
                    ]),
                ]);
            });

            Log::info('ProcessPortfolioFile: completed.', [
                'id'             => $file->id,
                'holdings_saved' => count($holdings),
            ]);

            // Email outside transaction — safe to queue after commit
            if ($reportPath && empty($file->fresh()->meta['extracted_from_zip_id'] ?? null)) {
                try {
                    $this->dispatchReportEmails($file, $riskScore);
                } catch (\Throwable $e) {
                    Log::error('ProcessPortfolioFile: failed to dispatch report emails.', [
                        'id'      => $file->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Throwable $e) {
            // Orphan PDF cleanup — DB rolled back but disk file remains if PDF was written
            if (!empty($reportPath) && Storage::disk(self::DISK)->exists($reportPath)) {
                Storage::disk(self::DISK)->delete($reportPath);
            }

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

    private function generatePdfReport(PortfolioFile $file, RiskScore $riskScore, ?int $portfolioId): string
    {
        $assets = $portfolioId
            ? PortfolioAsset::where('portfolio_id', $portfolioId)->orderByDesc('risk_score')->get()
            : collect();

        $portfolio = $portfolioId ? Portfolio::find($portfolioId) : null;

        $pdf  = Pdf::loadView('reports.risk-report', compact('portfolio', 'riskScore', 'assets', 'file'));
        $path = 'reports/' . now()->format('Y/m') . '/' . Str::uuid()->toString() . '.pdf';

        Storage::disk(self::DISK)->put($path, $pdf->output());

        return $path;
    }

    private function dispatchReportEmails(PortfolioFile $file, RiskScore $riskScore): void
    {
        $file->loadMissing(['user', 'portfolio']);

        Mail::to(env('REPORTS_NOTIFY_EMAIL'))->queue(new RiskReportMail($file, $riskScore));

        if ($file->user->email_reports && $file->user->email !== env('REPORTS_NOTIFY_EMAIL')) {
            Mail::to($file->user->email)->queue(new RiskReportMail($file, $riskScore));
        }
    }

    private function handleZipExtraction(PortfolioFile $file, string $absolutePath): void
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portfolio_zip_' . uniqid('', true);
        mkdir($tempDir, 0755, true);

        try {
            $zip    = new \ZipArchive();
            $result = $zip->open($absolutePath);

            if ($result !== true) {
                throw new \Exception("Failed to open ZIP archive (ZipArchive error code: {$result}).");
            }

            $zip->extractTo($tempDir);
            $zip->close();

            $realTempDir = realpath($tempDir);
            $iterator    = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            $childFiles  = [];
            $skipReasons = [];
            $nameCounts  = [];
            $nameIndex   = 0;

            foreach ($iterator as $extractedFile) {
                if ($extractedFile->isDir()) {
                    continue;
                }

                $realFilePath = realpath($extractedFile->getPathname());

                if ($realFilePath === false || !str_starts_with($realFilePath, $realTempDir)) {
                    $skipReasons[$extractedFile->getFilename()] = 'Security: path traversal detected';
                    continue;
                }

                $originalName = $extractedFile->getFilename();

                if (str_starts_with($originalName, '.') || str_starts_with($originalName, '__')) {
                    continue;
                }

                $ext = strtolower($extractedFile->getExtension());

                if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    $skipReasons[$originalName] = 'Unsupported file type: .' . $ext;
                    continue;
                }

                if ($extractedFile->getSize() === 0) {
                    $skipReasons[$originalName] = 'File is empty (0 bytes)';
                    continue;
                }

                $nameIndex++;
                $clientName              = $this->deriveClientName($originalName, $nameIndex);
                $nameCounts[$clientName] = ($nameCounts[$clientName] ?? 0) + 1;
                $finalName               = $nameCounts[$clientName] === 1
                    ? $clientName
                    : $clientName . ' ' . $nameCounts[$clientName];

                $portfolio = Portfolio::create([
                    'user_id' => $file->user_id,
                    'name'    => $finalName,
                ]);

                $directory      = now()->format('Y/m');
                $storedFilename = Str::uuid()->toString() . '.' . $ext;
                $storedPath     = $directory . '/' . $storedFilename;

                Storage::disk(self::DISK)->put($storedPath, file_get_contents($realFilePath));

                $childFile = PortfolioFile::create([
                    'user_id'       => $file->user_id,
                    'portfolio_id'  => $portfolio->id,
                    'original_name' => $originalName,
                    'stored_name'   => $storedFilename,
                    'path'          => $storedPath,
                    'mime_type'     => self::MIME_MAP[$ext] ?? 'application/octet-stream',
                    'file_size'     => $extractedFile->getSize(),
                    'status'        => PortfolioFile::STATUS_PENDING,
                    'meta'          => [
                        'uploaded_at'             => now()->toIso8601String(),
                        'extension'               => $ext,
                        'extracted_from_zip_id'   => $file->id,
                        'extracted_from_zip_name' => $file->original_name,
                        'client_name'             => $finalName,
                    ],
                ]);

                $childFiles[] = $childFile;
            }

            if (empty($childFiles)) {
                $file->update([
                    'status' => PortfolioFile::STATUS_FAILED,
                    'meta'   => array_merge($file->meta ?? [], [
                        'failed_at'     => now()->toIso8601String(),
                        'error_message' => 'No valid client files found in ZIP archive.',
                        'skip_reasons'  => $skipReasons,
                    ]),
                ]);

                Log::warning('ZIP extraction: no valid files found.', [
                    'portfolio_file_id' => $file->id,
                    'skip_reasons'      => $skipReasons,
                ]);

                return;
            }

            $file->update([
                'meta' => array_merge($file->meta ?? [], [
                    'extension'             => 'zip',
                    'extracted_files_count' => count($childFiles),
                    'skip_reasons'          => $skipReasons,
                ]),
            ]);

            $parentId = $file->id;
            $jobs     = array_map(fn($cf) => new self($cf), $childFiles);

            Bus::batch($jobs)
                ->finally(function () use ($parentId) {
                    AssembleBundleZip::dispatch($parentId);
                })
                ->dispatch();

            Log::info('ZIP archive extracted and batch queued.', [
                'portfolio_file_id' => $file->id,
                'user_id'           => $file->user_id,
                'child_count'       => count($childFiles),
                'skipped'           => count($skipReasons),
            ]);

        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    private function deriveClientName(string $filename, int $index): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $name = mb_convert_case(trim(str_replace(['-', '_'], ' ', $base)), MB_CASE_TITLE, 'UTF-8');

        if ($name === '' || in_array(strtolower($name), self::GENERIC_BASENAMES, true)) {
            return 'Client ' . $index;
        }

        return $name;
    }

    private function cleanupTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getRealPath());
            } else {
                @unlink($fileInfo->getRealPath());
            }
        }

        @rmdir($dir);
    }
}
