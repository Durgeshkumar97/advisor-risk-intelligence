<?php

namespace App\Jobs;

use App\Mail\BundleReportMail;
use App\Models\PortfolioFile;
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

        $children = PortfolioFile::whereRaw(
            'CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.extracted_from_zip_id")) AS UNSIGNED) = ?',
            [$this->parentFileId]
        )->get();

        if ($children->isEmpty()) {
            Log::warning('AssembleBundleZip: no children found.', ['id' => $this->parentFileId]);

            return;
        }

        $skipReasons = $parent->meta['skip_reasons'] ?? [];
        $withReport = $children->filter(fn ($c) => $c->isProcessed() && $c->report_path);
        $without = $children->reject(fn ($c) => $c->isProcessed() && $c->report_path);

        $tempZipPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.Str::uuid()->toString().'-bundle.zip';

        $assembled = false;

        try {
            $zip = new \ZipArchive;
            $zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            foreach ($withReport as $child) {
                $clientName = $child->meta['client_name'] ?? pathinfo($child->original_name, PATHINFO_FILENAME);
                $pdfName = Str::slug($clientName, '_').'_report.pdf';
                $zip->addFromString($pdfName, Storage::disk(self::DISK)->get($child->report_path));
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
                    'bundle_failed_count' => $without->count() + \count($skipReasons),
                ]),
            ]);

            $assembled = true;

            Log::info('AssembleBundleZip: bundle created.', [
                'parent_id' => $this->parentFileId,
                'processed' => $withReport->count(),
                'failed' => $without->count() + \count($skipReasons),
            ]);

            $parent->loadMissing('user');

            Mail::to(env('REPORTS_NOTIFY_EMAIL'))->queue(new BundleReportMail($parent));

            if ($parent->user->email_reports && $parent->user->email !== env('REPORTS_NOTIFY_EMAIL')) {
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
        $totalFound = $withReport->count() + $without->count() + count($skipReasons);

        $lines = [
            'RiskSignal — Multi-Client Portfolio Report Bundle',
            str_repeat('=', 50),
            'Source ZIP:  '.($parent->original_name ?? 'unknown'),
            'Generated:   '.now()->format('d M Y H:i:s').' UTC',
            '',
            'SUMMARY',
            '-------',
            'Total files found:       '.$totalFound,
            'Successfully processed:  '.$withReport->count(),
            'Failed / skipped:        '.($without->count() + count($skipReasons)),
        ];

        if ($without->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'FAILED DURING PROCESSING';
            $lines[] = '------------------------';
            foreach ($without as $f) {
                $reason = $f->meta['error_message'] ?? 'Processing failed — no report generated';
                $lines[] = '  '.$f->original_name.': '.$reason;
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
            foreach ($withReport as $p) {
                $clientName = $p->meta['client_name'] ?? pathinfo($p->original_name, PATHINFO_FILENAME);
                $lines[] = '  OK  '.$clientName.' ('.$p->original_name.')';
            }
        }

        return implode("\n", $lines);
    }
}
