<?php

namespace App\Jobs;

use App\Mail\BundleReportMail;
use App\Models\PortfolioFile;
use App\Services\ZipClientLayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssembleBundleZip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    private const DISK = 'portfolios';

    public function __construct(public readonly int $parentFileId) {}

    public function handle(): void
    {
        $parent = PortfolioFile::find($this->parentFileId);

        if (! $parent) {
            Log::warning('AssembleBundleZip: parent PortfolioFile not found.', ['id' => $this->parentFileId]);

            return;
        }

        // meta->key rather than raw JSON_UNQUOTE(JSON_EXTRACT(...)): Laravel
        // compiles the operator per driver, so this runs on MySQL in production
        // AND on the SQLite used locally and in tests. The raw form silently
        // made this job untestable — SQLite has json_extract but no
        // JSON_UNQUOTE — which is why nothing covered ZIP bundling at all.
        $children = PortfolioFile::where('meta->extracted_from_zip_id', $this->parentFileId)->get();

        if ($children->isEmpty()) {
            Log::warning('AssembleBundleZip: no children found.', ['id' => $this->parentFileId]);

            return;
        }

        $skipReasons = $parent->meta['skip_reasons'] ?? [];
        $failedClients = $parent->meta['failed_clients'] ?? [];

        // One client per portfolio. A client folder has several source files
        // and one lead file that carries the client's report; a file at the
        // ZIP root is a client on its own.
        $clients = $children->groupBy('portfolio_id')->map(function ($files) {
            $lead = $files->first(fn ($f) => empty($f->meta['merged_into_file_id'])) ?? $files->first();

            return [
                'lead' => $lead,
                'files' => $files,
                'name' => $lead->meta['client_name'] ?? pathinfo($lead->original_name, PATHINFO_FILENAME),
            ];
        })->values();

        $hasReport = fn (array $client) => $client['lead']->isProcessed() && $client['lead']->report_path;
        $withReport = $clients->filter($hasReport)->values();
        $without = $clients->reject($hasReport)->values();
        $failedCount = $without->count() + \count($failedClients) + \count($skipReasons);

        $tempZipPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.Str::uuid()->toString().'-bundle.zip';

        $assembled = false;

        try {
            $zip = new \ZipArchive;
            $zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            // Unique names: two clients whose names reduce to the same slug
            // (or to nothing, for a non-Latin name) must not overwrite each other.
            $usedNames = [];

            foreach ($withReport as $client) {
                $base = Str::slug($client['name'], '_') ?: 'client';
                $usedNames[$base] = ($usedNames[$base] ?? 0) + 1;
                $pdfName = $base.($usedNames[$base] > 1 ? '_'.$usedNames[$base] : '').'_report.pdf';

                $zip->addFromString($pdfName, Storage::disk(self::DISK)->get($client['lead']->report_path));
            }

            $zip->addFromString('_SUMMARY.txt', $this->buildSummary($parent, $withReport, $without));
            $zip->close();

            $bundlePath = 'reports/'.now()->format('Y/m').'/'.Str::uuid()->toString().'-bundle.zip';
            Storage::disk(self::DISK)->put($bundlePath, file_get_contents($tempZipPath));

            $parent->update([
                'status' => PortfolioFile::STATUS_PROCESSED,
                'processed_at' => now(),
                'bundle_report_path' => $bundlePath,
                'meta' => array_merge($parent->meta ?? [], [
                    'processing_completed_at' => now()->toIso8601String(),
                    'bundle_processed_count' => $withReport->count(),
                    'bundle_failed_count' => $failedCount,
                ]),
            ]);

            $assembled = true;

            Log::info('AssembleBundleZip: bundle created.', [
                'parent_id' => $this->parentFileId,
                'processed' => $withReport->count(),
                'failed' => $failedCount,
            ]);

            $parent->loadMissing('user');

            if ($parent->user->email_reports) {
                Mail::to($parent->user->email)->queue(new BundleReportMail($parent));
            }

        } catch (\Throwable $e) {
            Log::error('AssembleBundleZip: failed.', [
                'parent_id' => $this->parentFileId,
                'message' => $e->getMessage(),
            ]);

            if (! $assembled && $parent) {
                $parent->update([
                    'status' => PortfolioFile::STATUS_FAILED,
                    'meta' => array_merge($parent->meta ?? [], [
                        'failed_at' => now()->toIso8601String(),
                        'error_message' => $e->getMessage(),
                    ]),
                ]);
            }

            throw $e;
        } finally {
            @unlink($tempZipPath);
        }
    }

    private function buildSummary(PortfolioFile $parent, $withReport, $without): string
    {
        $skipReasons = $parent->meta['skip_reasons'] ?? [];
        $failedClients = $parent->meta['failed_clients'] ?? [];
        $clientSkipReasons = $parent->meta['client_skip_reasons'] ?? [];
        $notes = $parent->meta['zip_notes'] ?? [];
        $failedCount = $without->count() + count($failedClients) + count($skipReasons);

        $isFolder = fn (array $client) => ! empty($client['lead']->meta['client_folder']);

        $lines = [
            'RiskSignal — Multi-Client Portfolio Report Bundle',
            str_repeat('=', 50),
            'Source ZIP:  '.($parent->original_name ?? 'unknown'),
            'Generated:   '.now()->format('d M Y H:i:s').' UTC',
            '',
            'SUMMARY',
            '-------',
            'Total clients found:     '.($withReport->count() + $failedCount),
            'Successfully processed:  '.$withReport->count(),
            'Failed / skipped:        '.$failedCount,
        ];

        if ($notes !== []) {
            $lines[] = '';
            $lines[] = 'NOTES';
            $lines[] = '-----';
            foreach ($notes as $note) {
                $lines[] = '  '.$note;
            }
        }

        if ($without->isNotEmpty() || $failedClients !== []) {
            $lines[] = '';
            $lines[] = 'FAILED DURING PROCESSING';
            $lines[] = '------------------------';
            foreach ($without as $client) {
                $reason = $client['lead']->meta['error_message'] ?? 'Processing failed — no report generated';
                $label = $isFolder($client) ? $client['name'] : $client['lead']->original_name;
                $lines[] = '  '.$label.': '.$reason;
            }
            foreach ($failedClients as $name => $reason) {
                $lines[] = '  '.$name.': '.$reason;
                foreach ($clientSkipReasons[$name] ?? [] as $entry => $fileReason) {
                    $lines[] = '      skipped: '.ZipClientLayout::labelWithinClient((string) $entry).' — '.$fileReason;
                }
            }
        }

        if (! empty($skipReasons)) {
            $lines[] = '';
            $lines[] = 'SKIPPED BEFORE PROCESSING';
            $lines[] = '-------------------------';
            foreach ($skipReasons as $name => $reason) {
                $lines[] = '  '.$name.': '.$reason;
            }
        }

        if ($withReport->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'PROCESSED CLIENTS';
            $lines[] = '-----------------';
            foreach ($withReport as $client) {
                if (! $isFolder($client)) {
                    $lines[] = '  OK  '.$client['name'].' ('.$client['lead']->original_name.')';

                    continue;
                }

                // One line per client folder: the files its portfolio was
                // built from, then every file that was left out and why.
                $sources = $client['lead']->meta['client_sources'] ?? [];
                // Both maps are keyed by the full name inside the ZIP, so a
                // union can never drop a reason.
                $skipped = ($sources['skipped'] ?? []) + ($clientSkipReasons[$client['name']] ?? []);

                $lines[] = '  OK  '.$client['name'].' — built from: '.implode(', ', $sources['included'] ?? []);

                if ($skipped !== []) {
                    $lines[] = '      NOT INCLUDED in this client\'s portfolio:';
                    foreach ($skipped as $entry => $reason) {
                        $lines[] = '        '.ZipClientLayout::labelWithinClient((string) $entry).' — '.$reason;
                    }
                }
            }
        }

        return implode("\n", $lines);
    }
}
