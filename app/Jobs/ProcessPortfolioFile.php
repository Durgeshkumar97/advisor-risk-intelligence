<?php

namespace App\Jobs;

use App\Mail\RiskReportMail;
use App\Models\MarketRiskSnapshot;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Services\RiskEngine\AssetRiskScorer;
use App\Services\RiskEngine\HoldingsMerger;
use App\Services\RiskEngine\PortfolioParser;
use App\Services\RiskEngine\PortfolioRiskCalculator;
use App\Services\StockRiskService;
use App\Services\ZipClientLayout;
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

    public int $timeout = 300;

    public int $tries = 3;

    public int $backoff = 60;

    public int $maxExceptions = 3;

    private const DISK = 'portfolios';

    private const ALLOWED_EXTENSIONS = ['csv', 'xlsx', 'xls', 'pdf'];

    private const MIME_MAP = [
        'csv' => 'text/csv',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'pdf' => 'application/pdf',
    ];

    private const GENERIC_BASENAMES = [
        'book1', 'untitled', 'sheet1', 'empty', 'portfolio',
        'data', 'export', 'file', 'document', 'upload',
    ];

    private const MAX_DISTINCT_STOCK_SYMBOLS = 500;

    /**
     * Shown to the advisor when a file parses cleanly but yields no scoreable
     * holdings — almost always a value column PortfolioParser::ALIASES doesn't
     * recognise, so name the columns it does look for.
     */
    private const NO_HOLDINGS_MESSAGE = 'No valid holdings found. Check that your file has recognisable column headers (e.g. ISIN, Symbol, Quantity, Value).';

    public function __construct(
        public readonly PortfolioFile $portfolioFile
    ) {}

    public function handle(
        PortfolioParser $parser,
        AssetRiskScorer $assetScorer,
        PortfolioRiskCalculator $calculator,
        StockRiskService $stockRiskService,
        HoldingsMerger $merger
    ): void {
        $file = $this->portfolioFile->fresh();

        if (! $file) {
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
                'meta' => array_merge($file->meta ?? [], [
                    'processing_started_at' => now()->toIso8601String(),
                    'attempt' => $this->attempts(),
                ]),
            ]);

            Log::info('ProcessPortfolioFile: started.', [
                'id' => $file->id,
                'user_id' => $file->user_id,
                'filename' => $file->original_name,
            ]);

            if (! Storage::disk(self::DISK)->exists($file->path)) {
                throw new \RuntimeException('Portfolio file missing from storage: '.$file->path);
            }

            $extension = strtolower(pathinfo($file->path, PATHINFO_EXTENSION));
            $absolutePath = Storage::disk(self::DISK)->path($file->path);

            if ($extension === 'zip') {
                $this->handleZipExtraction($file, $absolutePath);

                return;
            }

            // A client folder from a ZIP: every broker file in the folder is
            // parsed and the holdings are merged into ONE portfolio before
            // scoring. Anything else is a single file, parsed as before.
            $clientSources = $this->clientSourceFiles($file);
            $parseResult = $clientSources === null
                ? $parser->parse($file)
                : $this->parseClientSources($clientSources, $parser, $merger, $file->meta['client_skipped_at_extraction'] ?? []);
            $holdings = $parseResult['rows'];
            $parseErrors = $parseResult['errors'];
            $parseWarnings = $parseResult['warnings'] ?? [];

            Log::info('ProcessPortfolioFile: parsed.', [
                'id' => $file->id,
                'rows_found' => count($holdings),
                'parse_errors' => $parseErrors,
            ]);

            $this->assertAllAmountsAreRupees($holdings);

            $portfolioId = $file->portfolio_id;
            $riskScore = null;
            $reportPath = null;

            $stockSymbols = collect($holdings)
                ->filter(fn ($row) => ($row['asset_type'] ?? null) === 'stock')
                ->map(fn ($row) => trim((string) ($row['symbol'] ?? '')))
                ->filter(fn ($symbol) => $symbol !== '')
                ->unique()
                ->values()
                ->all();

            if (count($stockSymbols) > self::MAX_DISTINCT_STOCK_SYMBOLS) {
                Log::warning('ProcessPortfolioFile: rejected — too many distinct stock symbols.', [
                    'id' => $file->id,
                    'distinct_symbol_count' => count($stockSymbols),
                    'max_allowed' => self::MAX_DISTINCT_STOCK_SYMBOLS,
                ]);

                $file->update([
                    'status' => PortfolioFile::STATUS_FAILED,
                    'meta' => array_merge($file->meta ?? [], [
                        'failed_at' => now()->toIso8601String(),
                        'error_message' => 'Portfolio contains too many distinct stock symbols ('.number_format(count($stockSymbols)).'). Maximum allowed is '.number_format(self::MAX_DISTINCT_STOCK_SYMBOLS).' — please split into smaller batches.',
                    ]),
                ]);

                return;
            }

            // Batch-fetch stock classifications OUTSIDE the transaction
            $stockRiskMap = $stockRiskService->classifyBatch($stockSymbols);

            // Fetch latest market risk snapshot OUTSIDE the transaction —
            // read-only DB call, same pattern as StockRiskService above.
            // If no snapshot exists yet (market-risk:sync not run), falls
            // back gracefully — multiplier stays at env default, no context
            // block written to meta.
            $marketSnapshot = MarketRiskSnapshot::latest();

            if ($marketSnapshot) {
                Log::info('ProcessPortfolioFile: market risk context loaded.', [
                    'id'               => $file->id,
                    'market_date'      => $marketSnapshot->market_date->toDateString(),
                    'market_label'     => $marketSnapshot->label,
                    'market_score'     => $marketSnapshot->score,
                    'multiplier_used'  => $marketSnapshot->multiplier(),
                ]);
            } else {
                Log::warning('ProcessPortfolioFile: no market risk snapshot found — using env default multiplier.', [
                    'id' => $file->id,
                ]);
            }

            DB::transaction(function () use (
                $file, $holdings, $portfolioId, $assetScorer, $calculator, $extension, $parseErrors, $parseWarnings, $parseResult,
                $stockRiskMap, $marketSnapshot, &$riskScore, &$reportPath
            ) {
                $lockedFile = PortfolioFile::lockForUpdate()->find($file->id);
                if (! $lockedFile || $lockedFile->status === PortfolioFile::STATUS_PROCESSED) {
                    return;
                }

                if ($portfolioId) {
                    PortfolioAsset::where('portfolio_id', $portfolioId)->delete();
                }

                $assetModels = collect();

                foreach ($holdings as $row) {
                    $isStock = ($row['asset_type'] ?? null) === 'stock';
                    $symbol = $isStock ? trim((string) ($row['symbol'] ?? '')) : '';
                    $stockRisk = ($isStock && $symbol !== '') ? ($stockRiskMap[$symbol] ?? null) : null;

                    $scored = $assetScorer->score($row['asset_type'], $row['name'], $stockRisk);
                    $assetScore = $scored['score'];

                    $meta = [
                        'source_file_id' => $file->id,
                        // file | derived (quantity × cost price) | unknown (stored as null, not 0)
                        'invested_value_source' => $row['invested_value_source'] ?? null,
                    ];

                    // Merged client folder: where each figure came from.
                    if (isset($row['sources'])) {
                        $meta += [
                            'sources' => $row['sources'],
                            'currency' => $row['currency'],
                            'value_basis' => $row['value_basis'],
                            'cost_known' => $row['cost_known'],
                            'as_of' => $row['as_of'],
                        ];
                    }

                    // A holding whose source figures were not rupees: the rate
                    // it was converted at, and the amounts as the file gave them.
                    if (isset($row['fx'])) {
                        $meta = array_merge($meta, [
                            'currency' => $row['currency'],
                            'value_basis' => $row['value_basis'],
                            'fx_rate' => $row['fx']['rate'],
                            'fx_as_of' => $row['fx']['as_of'],
                            'fx_source' => $row['fx']['source'],
                            'original' => $row['original'],
                        ]);
                    }

                    if ($isStock) {
                        $meta['stock_risk'] = [
                            'source' => $scored['source'],
                            'risk_level' => $stockRisk['risk_level'] ?? null,
                            'volatility' => $stockRisk['volatility'] ?? null,
                            'confidence' => $stockRisk['confidence'] ?? null,
                            'as_of_date' => $stockRisk['as_of_date'] ?? null,
                            'stale' => $stockRisk['stale'] ?? false,
                        ];
                    }

                    $asset = PortfolioAsset::create([
                        'portfolio_id' => $portfolioId,
                        'asset_type' => $row['asset_type'],
                        'symbol' => $row['symbol'],
                        'name' => $row['name'],
                        'isin' => $row['isin'],
                        'quantity' => $row['quantity'],
                        'buy_price' => $row['buy_price'],
                        'current_price' => $row['current_price'],
                        'invested_value' => $row['invested_value'],
                        'current_value' => $row['current_value'],
                        'profit_loss' => $row['profit_loss'],
                        'risk_score' => $assetScore,
                        'risk_level' => $assetScorer->level($assetScore),
                        'meta' => $meta,
                    ]);

                    $assetModels->push($asset);
                }

                if ($assetModels->isNotEmpty()) {
                    // Passed per-call rather than assigned into config(), which
                    // is process-global and leaked across jobs in one worker.
                    $result = $calculator->calculate($assetModels, $marketSnapshot?->multiplier());

                    // Build market context block — included directly in create()
                    // so meta is complete in one write, no create-then-update.
                    $marketContext = $marketSnapshot ? [
                        'market_context' => [
                            'date'            => $marketSnapshot->market_date->toDateString(),
                            'score'           => $marketSnapshot->score,
                            'score_smooth'    => $marketSnapshot->score_smooth,
                            'label'           => $marketSnapshot->label,
                            'warning_severity'=> $marketSnapshot->warning_severity,
                            'warning_text'    => $marketSnapshot->warning_text,
                            'vol_regime'      => $marketSnapshot->vol_regime,
                            'dd_regime'       => $marketSnapshot->dd_regime,
                            'market_regime'   => $marketSnapshot->market_regime,
                            'multiplier_used' => $marketSnapshot->multiplier(),
                        ],
                    ] : [];

                    $riskScore = RiskScore::create([
                        'user_id' => $file->user_id,
                        'portfolio_id' => $portfolioId,
                        'score' => $result['score'],
                        'volatility' => $result['volatility'],
                        'drawdown' => $result['drawdown'],
                        'generated_at' => now(),
                        'meta' => array_merge($result['meta'], [
                            'trigger' => 'file_upload',
                            'next_action' => $result['next_action'],
                            'risk_flags' => $result['risk_flags'],
                        ], $marketContext),
                    ]);

                    Log::info('ProcessPortfolioFile: risk score saved.', [
                        'id' => $file->id,
                        'score' => $result['score'],
                        'risk_level' => $result['meta']['risk_level'],
                        'asset_count' => count($assetModels),
                        'market_label' => $marketSnapshot?->label ?? 'unavailable',
                    ]);

                    // Must run AFTER the RiskScore exists — the portfolio's
                    // risk_score column now mirrors that composite rather than
                    // deriving its own average, so calling this earlier would
                    // copy the previous run's score (or none, on a first
                    // upload). Passed explicitly to avoid a re-query.
                    if ($portfolioId) {
                        $portfolio = Portfolio::find($portfolioId);
                        if ($portfolio) {
                            $portfolio->recalculateMetrics($riskScore);
                        }
                    }
                }

                $reportPath = $riskScore
                    ? $this->generatePdfReport($file, $riskScore, $portfolioId, $parseResult['client_sources'] ?? null)
                    : null;

                /*
                |----------------------------------------------------------------------
                | ZERO VALID HOLDINGS — fail loudly, don't report a silent success
                |----------------------------------------------------------------------
                |
                | No holdings means no RiskScore and no PDF. Marking this PROCESSED
                | showed the advisor "Processed ✓" next to an empty result with no
                | explanation.
                |
                | When the parser returns exactly ONE error it is a file-level
                | fatal it can describe precisely — an empty file, no recognisable
                | name column, or the MAX_ROWS rejection — so that message is
                | shown verbatim. Several errors means per-row skips, where the
                | useful thing to say is the generic "check your column headers".
                |
                */

                $failureMessage = self::NO_HOLDINGS_MESSAGE;

                if (! $riskScore) {
                    if (count($parseErrors) === 1) {
                        $failureMessage = $parseErrors[0];
                    } else {
                        $parseErrors = array_merge([$failureMessage], $parseErrors);
                    }
                }

                $file->update([
                    'status' => $riskScore
                        ? PortfolioFile::STATUS_PROCESSED
                        : PortfolioFile::STATUS_FAILED,
                    'processed_at' => now(),
                    'report_path' => $reportPath,
                    'meta' => array_merge($file->meta ?? [], [
                        'processing_completed_at' => now()->toIso8601String(),
                        'extension' => $extension,
                        'holdings_parsed' => count($holdings),
                        'parse_errors' => $parseErrors,
                        'parse_warnings' => $parseWarnings,
                    ] + (isset($parseResult['client_sources']) ? [
                        'client_sources' => $parseResult['client_sources'],
                    ] : []) + ($riskScore ? [] : [
                        'failed_at' => now()->toIso8601String(),
                        'error_message' => $failureMessage,
                    ])),
                ]);
            });

            Log::info('ProcessPortfolioFile: completed.', [
                'id' => $file->id,
                'holdings_saved' => count($holdings),
            ]);

            $this->recordSourceOutcomes($file, $parseResult['source_outcomes'] ?? [], $riskScore !== null);

            if ($reportPath && empty($file->fresh()->meta['extracted_from_zip_id'] ?? null)) {
                try {
                    $this->dispatchReportEmails($file, $riskScore);
                } catch (\Throwable $e) {
                    Log::error('ProcessPortfolioFile: failed to dispatch report emails.', [
                        'id' => $file->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Throwable $e) {
            if (! empty($reportPath) && Storage::disk(self::DISK)->exists($reportPath)) {
                Storage::disk(self::DISK)->delete($reportPath);
            }

            // The advisor is told only that processing failed. What was thrown
            // names tables, columns and paths; it goes to the log and to the
            // exception handler (Sentry) below, never into the file's record.
            $file->update([
                'status' => PortfolioFile::STATUS_FAILED,
                'meta' => array_merge($file->meta ?? [], [
                    'failed_at' => now()->toIso8601String(),
                    'error_message' => PortfolioFile::GENERIC_FAILURE_MESSAGE,
                ]),
            ]);

            // The other files of a client folder have no job of their own.
            PortfolioFile::whereIn('id', $file->meta['client_source_file_ids'] ?? [])
                ->where('id', '!=', $file->id)
                ->where('status', PortfolioFile::STATUS_PENDING)
                ->update(['status' => PortfolioFile::STATUS_FAILED]);

            Log::error('ProcessPortfolioFile: failed.', [
                'id' => $file->id,
                'user_id' => $file->user_id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            report($e);

            throw $e;
        }
    }

    /**
     * Last line of defence against mixing currencies: every amount stored and
     * scored is rupees. The parser converts a foreign-currency holding and
     * records the rate; a holding that claims another currency with no
     * conversion on record would be read as rupees, so nothing is stored.
     */
    private function assertAllAmountsAreRupees(array $holdings): void
    {
        foreach ($holdings as $row) {
            if (($row['currency'] ?? 'INR') !== 'INR' && empty($row['fx']['rate'])) {
                throw new \RuntimeException('A '.$row['currency'].' holding reached scoring without being converted to rupees. Nothing was stored.');
            }
        }
    }

    /**
     * @param  ?array  $clientSources  a client folder's included and skipped files, printed on
     *                                 the report; passed in because the file's meta does not
     *                                 hold them yet when the report is rendered
     */
    private function generatePdfReport(PortfolioFile $file, RiskScore $riskScore, ?int $portfolioId, ?array $clientSources = null): string
    {
        $assets = $portfolioId
            ? PortfolioAsset::where('portfolio_id', $portfolioId)->orderByDesc('risk_score')->get()
            : collect();

        $portfolio = $portfolioId ? Portfolio::find($portfolioId) : null;

        $pdf = Pdf::loadView('reports.risk-report', compact('portfolio', 'riskScore', 'assets', 'file', 'clientSources'));
        $path = 'reports/'.now()->format('Y/m').'/'.Str::uuid()->toString().'.pdf';

        Storage::disk(self::DISK)->put($path, $pdf->output());

        return $path;
    }

    private function dispatchReportEmails(PortfolioFile $file, RiskScore $riskScore): void
    {
        $file->loadMissing(['user', 'portfolio']);

        if ($file->user->email_reports) {
            Mail::to($file->user->email)->queue(new RiskReportMail($file, $riskScore));
        }
    }

    private function handleZipExtraction(PortfolioFile $file, string $absolutePath): void
    {
        if (PortfolioFile::where('meta->extracted_from_zip_id', $file->id)->exists()) {
            Log::info('ZIP extraction: children already exist, skipping re-extraction.', ['id' => $file->id]);

            return;
        }

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'portfolio_zip_'.uniqid('', true);
        $this->createOwnerOnlyTempDir($tempDir);

        try {
            $zip = new \ZipArchive;
            $result = $zip->open($absolutePath);

            if ($result !== true) {
                throw new \Exception("Failed to open ZIP archive (ZipArchive error code: {$result}).");
            }

            $skipReasons = [];      // root files and unsafe entries, keyed by name
            $rejectedEntries = [];
            $rootFiles = [];        // accepted files at the ZIP root — one client each
            $folders = [];          // client name => files / skipped / nested

            // A ZIP made by compressing a parent folder has one outer folder
            // around the client folders; it is a container, not a client.
            $layoutNames = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if ($name !== false && ! ZipClientLayout::isDirectory($name) && ! ZipClientLayout::isIgnored($name)) {
                    $layoutNames[] = $name;
                }
            }

            $wrapper = ZipClientLayout::wrapperFolder($layoutNames);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if ($name === false) {
                    continue;
                }

                if ($this->isUnsafeZipEntryName($name)) {
                    $rejectedEntries[] = $name;
                    $skipReasons[$name] = 'Unsafe entry name — not extracted';

                    continue;
                }

                if (ZipClientLayout::isDirectory($name) || ZipClientLayout::isIgnored($name)) {
                    continue;
                }

                $name = ZipClientLayout::unwrap($name, $wrapper);
                $filename = ZipClientLayout::filename($name);
                $folder = ZipClientLayout::folder($name);
                $clientName = $folder === null ? null : ZipClientLayout::clientName($folder);

                if ($clientName !== null) {
                    $folders[$clientName] ??= ['files' => [], 'skipped' => [], 'nested' => false];
                    $folders[$clientName]['nested'] = $folders[$clientName]['nested'] || ZipClientLayout::isNested($name);
                }

                $entry = $this->extractZipEntry($zip, $i, $filename, $tempDir);

                if (isset($entry['skip'])) {
                    if ($clientName === null) {
                        $skipReasons[$filename] = $entry['skip'];
                    } else {
                        // Keyed by the full entry name: two same-named files in
                        // different subfolders must each keep their own reason.
                        $folders[$clientName]['skipped'][$name] = $entry['skip'];
                    }

                    continue;
                }

                $entry['zip_entry'] = $name;

                if ($clientName === null) {
                    $rootFiles[] = $entry;
                } else {
                    $folders[$clientName]['files'][] = $entry;
                }
            }

            $zip->close();

            if (! empty($rejectedEntries)) {
                Log::warning('ZIP extraction: rejected unsafe entry names before extraction.', [
                    'portfolio_file_id' => $file->id,
                    'rejected_entries' => $rejectedEntries,
                ]);
            }

            /*
            |------------------------------------------------------------------
            | CLIENTS
            |------------------------------------------------------------------
            | A file at the root is one client, named from its filename (the
            | pre-folder behaviour). A top-level folder is one client, named
            | from the folder, however many broker files it holds.
            */

            $clients = [];
            $failedClients = [];
            $clientSkipReasons = [];
            $nameCounts = [];
            $nameIndex = 0;

            $uniqueName = function (string $name) use (&$nameCounts): string {
                $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;

                return $nameCounts[$name] === 1 ? $name : $name.' '.$nameCounts[$name];
            };

            foreach ($rootFiles as $entry) {
                $nameIndex++;
                $clients[] = [
                    'name' => $uniqueName($this->deriveClientName($entry['original_name'], $nameIndex)),
                    'files' => [$entry],
                    'folder' => false,
                ];
            }

            foreach ($folders as $folderClient => $folderData) {
                $nameIndex++;
                $name = $uniqueName($folderClient === '' ? 'Client '.$nameIndex : $folderClient);

                // A byte-identical file twice in one folder is an accidental
                // duplicate download; summing it would double the portfolio.
                $files = [];
                $skipped = $folderData['skipped'];
                $seenHashes = [];

                foreach ($folderData['files'] as $entry) {
                    if (isset($seenHashes[$entry['sha256']])) {
                        $skipped[$entry['zip_entry']] = 'Duplicate of '.ZipClientLayout::labelWithinClient($seenHashes[$entry['sha256']]).' (identical file)';

                        continue;
                    }

                    $seenHashes[$entry['sha256']] = $entry['zip_entry'];
                    $files[] = $entry;
                }

                if (count($files) >= 5) {
                    Log::info('ZIP extraction: client folder with 5 or more files.', [
                        'user_id' => $file->user_id,
                        'file_count' => count($files),
                    ]);
                }

                if ($skipped !== []) {
                    $clientSkipReasons[$name] = $skipped;
                }

                if (count($files) > ZipClientLayout::MAX_FILES_PER_CLIENT) {
                    $failedClients[$name] = sprintf(
                        'This client folder has %d files; the maximum is %d per client.',
                        count($files),
                        ZipClientLayout::MAX_FILES_PER_CLIENT,
                    );

                    continue;
                }

                if ($files === []) {
                    $failedClients[$name] = 'No usable files in this client folder.';

                    continue;
                }

                $clients[] = ['name' => $name, 'files' => $files, 'folder' => true, 'skipped' => $skipped];
            }

            if (count($clients) > ZipClientLayout::MAX_CLIENTS) {
                $file->update([
                    'status' => PortfolioFile::STATUS_FAILED,
                    'meta' => array_merge($file->meta ?? [], [
                        'failed_at' => now()->toIso8601String(),
                        'error_message' => sprintf(
                            'This ZIP has %s clients; the maximum is %s per upload — please split it into smaller batches.',
                            number_format(count($clients)),
                            number_format(ZipClientLayout::MAX_CLIENTS),
                        ),
                    ]),
                ]);

                return;
            }

            $leadFiles = [];
            $childCount = 0;

            foreach ($clients as $client) {
                $portfolio = Portfolio::create([
                    'user_id' => $file->user_id,
                    'name' => $client['name'],
                ]);

                $rows = [];

                foreach ($client['files'] as $entry) {
                    // Stored under a UUID; no name from the ZIP is ever used
                    // to build a path.
                    $storedFilename = Str::uuid()->toString().'.'.$entry['ext'];
                    $storedPath = now()->format('Y/m').'/'.$storedFilename;

                    Storage::disk(self::DISK)->put($storedPath, file_get_contents($entry['temp_path']));

                    $rows[] = PortfolioFile::create([
                        'user_id' => $file->user_id,
                        'portfolio_id' => $portfolio->id,
                        'original_name' => $entry['original_name'],
                        'stored_name' => $storedFilename,
                        'path' => $storedPath,
                        'mime_type' => $entry['mime'],
                        'file_size' => $entry['size'],
                        'status' => PortfolioFile::STATUS_PENDING,
                        'meta' => [
                            'uploaded_at' => now()->toIso8601String(),
                            'extension' => $entry['ext'],
                            'extracted_from_zip_id' => $file->id,
                            'extracted_from_zip_name' => $file->original_name,
                            'client_name' => $client['name'],
                        ] + ($client['folder'] ? [
                            'client_folder' => true,
                            'zip_entry' => $entry['zip_entry'],
                            'content_sha256' => $entry['sha256'],
                        ] : []),
                    ]);
                }

                // The first file leads: it carries the client's one job and
                // one report. The others are sources merged into it.
                $lead = $rows[0];

                if ($client['folder']) {
                    foreach (array_slice($rows, 1) as $source) {
                        $source->update(['meta' => array_merge($source->meta, ['merged_into_file_id' => $lead->id])]);
                    }

                    // Files of this client dropped during extraction travel with
                    // the lead, so the upload-history warning names them too —
                    // not only _SUMMARY.txt.
                    $lead->update(['meta' => array_merge($lead->meta, [
                        'client_source_file_ids' => array_map(fn ($row) => $row->id, $rows),
                        'client_skipped_at_extraction' => $client['skipped'],
                    ])]);
                }

                $leadFiles[] = $lead;
                $childCount += count($rows);
            }

            $clientDetails = [
                'failed_clients' => $failedClients,
                'client_skip_reasons' => $clientSkipReasons,
                'zip_notes' => $this->zipLayoutNotes($folders, $rootFiles, $wrapper),
            ];

            if (empty($leadFiles)) {
                $file->update([
                    'status' => PortfolioFile::STATUS_FAILED,
                    'meta' => array_merge($file->meta ?? [], [
                        'failed_at' => now()->toIso8601String(),
                        'error_message' => 'No valid client files found in ZIP archive.',
                        'skip_reasons' => $skipReasons,
                    ], $clientDetails),
                ]);

                Log::warning('ZIP extraction: no valid files found.', [
                    'portfolio_file_id' => $file->id,
                    'skip_reasons' => $skipReasons,
                ]);

                return;
            }

            $file->update([
                'meta' => array_merge($file->meta ?? [], [
                    'extension' => 'zip',
                    'extracted_files_count' => $childCount,
                    'client_count' => count($leadFiles),
                    'skip_reasons' => $skipReasons,
                ], $clientDetails),
            ]);

            $parentId = $file->id;
            $jobs = array_map(fn ($lead) => new self($lead), $leadFiles);

            Bus::batch($jobs)
                ->finally(function () use ($parentId) {
                    AssembleBundleZip::dispatch($parentId);
                })
                ->dispatch();

            Log::info('ZIP archive extracted and batch queued.', [
                'portfolio_file_id' => $file->id,
                'user_id' => $file->user_id,
                'child_count' => $childCount,
                'client_count' => count($leadFiles),
                'skipped' => count($skipReasons),
            ]);

        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    /**
     * Copy one ZIP entry to a temp file and validate it.
     *
     * The temp name is built only from the entry's position in the archive and
     * its allow-listed extension, so no folder or file name supplied by the
     * ZIP ever reaches the filesystem.
     *
     * @return array{skip: string}|array{original_name: string, ext: string, temp_path: string, size: int, mime: string, sha256: string}
     */
    private function extractZipEntry(\ZipArchive $zip, int $index, string $filename, string $tempDir): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['skip' => 'Unsupported file type: .'.$ext];
        }

        $stat = $zip->statIndex($index);

        if (! $stat || $stat['size'] === 0) {
            return ['skip' => 'File is empty (0 bytes)'];
        }

        $tempPath = $tempDir.DIRECTORY_SEPARATOR.'entry_'.$index.'.'.$ext;
        $in = $zip->getStreamIndex($index);

        if ($in === false) {
            return ['skip' => 'Could not read this entry from the ZIP'];
        }

        $out = fopen($tempPath, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $detectedFile = new \Symfony\Component\HttpFoundation\File\File($tempPath);
        $result = \App\Rules\PortfolioFileType::contentMatchesAllowedType($detectedFile, $ext, self::ALLOWED_EXTENSIONS);

        if (! $result['acceptable']) {
            return ['skip' => 'File content does not match a supported type (claimed .'.$ext.', detected: '.($result['detectedExtension'] ?? 'unrecognized').')'];
        }

        return [
            'original_name' => $filename,
            'ext' => $ext,
            'temp_path' => $tempPath,
            'size' => filesize($tempPath),
            'mime' => $result['detectedMimeType'] ?: (self::MIME_MAP[$ext] ?? 'application/octet-stream'),
            'sha256' => hash_file('sha256', $tempPath),
        ];
    }

    /**
     * Plain-language notes about how the ZIP was read, for _SUMMARY.txt.
     *
     * @return list<string>
     */
    private function zipLayoutNotes(array $folders, array $rootFiles, ?string $wrapper = null): array
    {
        $notes = [];

        if ($wrapper !== null) {
            $notes[] = "Outer folder '{$wrapper}' was treated as a container.";
        }

        if ($folders !== [] && $rootFiles !== []) {
            $notes[] = 'This ZIP has both client folders and files at the top level. Each top-level file was treated as one client, named from its filename.';
        }

        foreach ($folders as $name => $folder) {
            if ($folder['nested']) {
                $notes[] = "Subfolders inside '{$name}' were ignored for naming: every file under a client folder belongs to that client.";
            }
        }

        $names = array_map('strval', array_keys($folders));

        foreach ($names as $i => $a) {
            foreach (array_slice($names, $i + 1) as $b) {
                if ($a !== $b && mb_strtolower($a) === mb_strtolower($b)) {
                    $notes[] = "Folders '{$a}' and '{$b}' differ only by case — treated as two clients.";
                }
            }
        }

        return $notes;
    }

    /*
    |--------------------------------------------------------------------------
    | CLIENT FOLDERS — several broker files, one portfolio
    |--------------------------------------------------------------------------
    */

    /**
     * The source files of a client folder when $file leads one, else null.
     */
    private function clientSourceFiles(PortfolioFile $file): ?\Illuminate\Support\Collection
    {
        if (empty($file->meta['client_folder'])) {
            return null;
        }

        return PortfolioFile::whereIn('id', $file->meta['client_source_file_ids'] ?? [$file->id])
            ->orderBy('id')
            ->get();
    }

    /**
     * Parse every source file of one client and merge the holdings.
     *
     * A file that cannot be used (unsupported, unparseable, US-dollar with no
     * exchange rate, or a
     * duplicate of another file's holdings) is skipped with its reason and the
     * client is scored from the rest. If nothing can be used the client fails
     * with that reason.
     */
    private function parseClientSources(\Illuminate\Support\Collection $sources, PortfolioParser $parser, HoldingsMerger $merger, array $skippedAtExtraction = []): array
    {
        $holdings = [];
        $errors = [];
        $warnings = [];
        $included = [];
        // Keyed by the file's full name inside the ZIP (unique), never by its
        // bare filename: two "holdings.csv" in different subfolders must both
        // keep their reason. Starts with what extraction already dropped.
        $skipped = $skippedAtExtraction;
        $fileCount = count($sources) + count($skippedAtExtraction);
        $outcomes = [];
        $fingerprints = [];

        foreach ($sources as $source) {
            $entryName = $source->meta['zip_entry'] ?? $source->original_name;
            $result = $parser->parse($source);

            if ($result['rows'] === []) {
                $reason = count($result['errors']) === 1 ? $result['errors'][0] : self::NO_HOLDINGS_MESSAGE;
            } elseif (isset($fingerprints[$fingerprint = $this->holdingsFingerprint($result['rows'])])) {
                $reason = 'Duplicate of '.$fingerprints[$fingerprint].' (identical holdings)';
            } else {
                $reason = null;
                $fingerprints[$fingerprint] = ZipClientLayout::labelWithinClient($entryName);
            }

            $outcomes[$source->id] = $reason;

            if ($reason !== null) {
                $skipped[$entryName] = $reason;

                continue;
            }

            $included[] = ZipClientLayout::labelWithinClient($entryName);
            $warnings = array_merge($warnings, $result['warnings'] ?? []);

            foreach ($result['errors'] as $error) {
                $errors[] = $source->original_name.': '.$error;
            }

            foreach ($result['rows'] as $row) {
                // Normalised holding: the parser's row plus where it came from.
                $holdings[] = $row + [
                    'source_file' => $source->original_name,
                    'currency' => 'INR',
                    'cost_known' => $row['invested_value'] !== null,
                    'value_basis' => 'market',
                    'as_of' => null,
                ];
            }
        }

        $describe = fn (array $map) => implode('; ', array_map(
            fn ($entry, $reason) => ZipClientLayout::labelWithinClient((string) $entry).' — '.$reason,
            array_keys($map),
            $map,
        ));

        if ($included === []) {
            $reasons = array_values(array_unique($skipped));
            $errors = [count($reasons) === 1
                ? $reasons[0]
                : sprintf("None of this client's %d files could be used: %s", $fileCount, $describe($skipped))];
        } elseif ($skipped !== []) {
            array_unshift($warnings, sprintf(
                'Scored from %d of %d files; skipped: %s',
                count($included),
                $fileCount,
                $describe($skipped),
            ));
        }

        $rows = $merger->merge($holdings);

        return [
            'rows' => $rows,
            'errors' => $errors,
            'warnings' => $warnings,
            'count' => count($rows),
            'client_sources' => ['included' => $included, 'skipped' => $skipped],
            'source_outcomes' => $outcomes,
        ];
    }

    /** Identical for two files that hold the same positions, whatever their bytes. */
    private function holdingsFingerprint(array $rows): string
    {
        $lines = array_map(fn ($row) => implode('|', [
            strtoupper((string) ($row['isin'] ?? '')) ?: mb_strtolower(trim(preg_replace('/\s+/u', ' ', $row['name']))),
            (string) $row['quantity'],
            (string) $row['current_value'],
        ]), $rows);

        sort($lines);

        return sha1(implode("\n", $lines));
    }

    /**
     * Mark the non-lead source files of a client folder with their outcome.
     *
     * @param  array<int, ?string>  $outcomes  file id => skip reason, or null if included
     */
    private function recordSourceOutcomes(PortfolioFile $lead, array $outcomes, bool $scored): void
    {
        foreach ($outcomes as $fileId => $reason) {
            if ($fileId === $lead->id) {
                continue;
            }

            $source = PortfolioFile::find($fileId);

            if (! $source) {
                continue;
            }

            $used = $reason === null && $scored;

            $source->update([
                'status' => $used ? PortfolioFile::STATUS_PROCESSED : PortfolioFile::STATUS_FAILED,
                'processed_at' => now(),
                'meta' => array_merge($source->meta ?? [], $used ? [] : [
                    'failed_at' => now()->toIso8601String(),
                    'error_message' => $reason ?? 'No report was produced for this client.',
                ]),
            ]);
        }
    }

    private function createOwnerOnlyTempDir(string $path): void
    {
        mkdir($path, 0700, true);
        chmod($path, 0700);
    }

    private function isUnsafeZipEntryName(string $name): bool
    {
        if (str_starts_with($name, '/') || preg_match('#^[a-zA-Z]:[\\\\/]#', $name)) {
            return true;
        }

        $segments = preg_split('#[\\\\/]+#', $name);

        return in_array('..', $segments, true);
    }

    private function deriveClientName(string $filename, int $index): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $name = mb_convert_case(trim(str_replace(['-', '_'], ' ', $base)), MB_CASE_TITLE, 'UTF-8');

        if ($name === '' || in_array(strtolower($name), self::GENERIC_BASENAMES, true)) {
            return 'Client '.$index;
        }

        return $name;
    }

    private function cleanupTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
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