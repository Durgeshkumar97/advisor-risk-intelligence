<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePortfolioUploadRequest;
use App\Http\Requests\StoreManualPortfolioRequest;
use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\Subscription;
use App\Exceptions\PortfolioUploadException;
use App\Services\PortfolioUploadService;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PortfolioUploadController extends Controller
{
    private const DISK = 'portfolios';

    public function __construct(
        private readonly PortfolioUploadService $uploadService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription || (!$subscription->isActive() && !$subscription->isTrial() && !$subscription->isInGracePeriod())) {
            return redirect()->route('pricing')
                ->with('error', 'An active subscription is required to upload portfolios.');
        }

        $plan               = $subscription->plan;
        $monthlyClientLimit = $plan->monthly_client_limit ?? 50;
        $monthlyClientCount = PortfolioFile::monthlyClientCount($user->id);
        $monthlyResetDate   = now()->addMonthNoOverflow()->startOfMonth()->format('d M Y');

        $portfolios = Portfolio::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $files = PortfolioFile::query()
            ->where('user_id', $user->id)
            ->with('portfolio')
            ->latest()
            ->get();

        return view('portfolio.upload', [
            'portfolios'         => $portfolios,
            'files'              => $files,
            'monthlyClientCount' => $monthlyClientCount,
            'monthlyClientLimit' => $monthlyClientLimit,
            'monthlyResetDate'   => $monthlyResetDate,
            'planName'           => $plan->name ?? 'Unknown',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(StorePortfolioUploadRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription || (!$subscription->isActive() && !$subscription->isTrial() && !$subscription->isInGracePeriod())) {
            return redirect()->route('pricing')
                ->with('error', 'An active subscription is required to upload portfolios.');
        }

        // Monthly client limit check.
        // NOTE: race window — concurrent uploads from the same user could both pass this check simultaneously.
        $limit        = $subscription->plan?->monthly_client_limit ?? 50;
        $currentCount = PortfolioFile::monthlyClientCount($user->id);
        $resetDate    = now()->addMonthNoOverflow()->startOfMonth()->format('d M Y');

        if (strtolower($request->getFile()->getClientOriginalExtension()) === 'zip') {
            $peekCount = $this->peekZipClientCount($request->getFile()->getRealPath());
            if ($peekCount > 0 && $currentCount + $peekCount > $limit) {
                $remaining = max(0, $limit - $currentCount);
                return back()
                    ->withErrors(['file' => "Monthly limit reached ({$currentCount}/{$limit} clients used this month). This ZIP has {$peekCount} client(s) but only {$remaining} slot(s) remain. Resets {$resetDate}."])
                    ->withInput();
            }
        } elseif ($currentCount >= $limit) {
            return back()
                ->withErrors(['file' => "Monthly limit reached ({$currentCount}/{$limit} clients used this month). Resets {$resetDate}."])
                ->withInput();
        }

        try {
            $portfolioFile = $this->uploadService->handleUpload(
                userId:      $user->id,
                file:        $request->getFile(),
                portfolioId: $request->getPortfolioId(),
            );

            ProcessPortfolioFile::dispatch($portfolioFile);

            return redirect()
                ->route('portfolio.upload')
                ->with('success', 'Portfolio uploaded successfully. Processing has started.');

        } catch (PortfolioUploadException $e) {
            Log::warning('Portfolio upload business error.', [
                'message' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return back()
                ->withErrors(['file' => $e->getUserMessage()])
                ->withInput();

        } catch (\Throwable $e) {
            Log::error('Portfolio upload failed unexpectedly.', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user_id' => $user->id,
            ]);

            return back()
                ->withErrors(['file' => 'Upload failed. Please try again.'])
                ->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STORE MANUAL — convert manual-entry form data into a CSV, then run the
    | exact same pipeline as a real file upload (same job, same disk, same UX).
    |--------------------------------------------------------------------------
    */

    public function storeManual(StoreManualPortfolioRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription || (!$subscription->isActive() && !$subscription->isTrial() && !$subscription->isInGracePeriod())) {
            return redirect()->route('pricing')
                ->with('error', 'An active subscription is required.');
        }

        $limit        = $subscription->plan?->monthly_client_limit ?? 50;
        $currentCount = PortfolioFile::monthlyClientCount($user->id);
        $resetDate    = now()->addMonthNoOverflow()->startOfMonth()->format('d M Y');

        if ($currentCount >= $limit) {
            return back()
                ->withErrors(['client_name' => "Monthly limit reached ({$currentCount}/{$limit} clients used this month). Resets {$resetDate}."])
                ->withInput();
        }

        try {
            $clientName  = $request->getClientName();
            $portfolioId = $request->getPortfolioId();
            $mode        = $request->input('mode');

            // Build rows and CSV
            if ($mode === 'holdings') {
                $rows = $request->getHoldingsRows();
            } else {
                $rows = array_map(
                    fn ($r) => ['name' => ucwords(str_replace('_', ' ', $r['type'])), 'type' => $r['type'], 'value' => $r['value']],
                    $request->getAllocRows()
                );
            }

            $csvContent = $this->buildCsv($rows);

            // Store as a .csv file on the portfolios disk
            $directory  = now()->format('Y/m');
            $filename   = Str::uuid()->toString() . '.csv';
            $storedPath = $directory . '/' . $filename;

            $written = Storage::disk(self::DISK)->put($storedPath, $csvContent);
            if (!$written) {
                throw new \RuntimeException('Failed to write generated CSV to storage.');
            }

            $originalName = $clientName . '.csv';
            $fileSize     = strlen($csvContent);

            $portfolioFile = PortfolioFile::create([
                'user_id'      => $user->id,
                'portfolio_id' => $portfolioId,
                'original_name'=> $originalName,
                'stored_name'  => $filename,
                'path'         => $storedPath,
                'mime_type'    => 'text/csv',
                'file_size'    => $fileSize,
                'status'       => PortfolioFile::STATUS_PENDING,
                'meta'         => [
                    'uploaded_at' => now()->toIso8601String(),
                    'extension'   => 'csv',
                    'source'      => 'manual_entry',
                    'mode'        => $mode,
                ],
            ]);

            ProcessPortfolioFile::dispatch($portfolioFile);

            Log::info('Manual portfolio entry submitted.', [
                'portfolio_file_id' => $portfolioFile->id,
                'user_id'           => $user->id,
                'mode'              => $mode,
                'row_count'         => count($rows),
            ]);

            return redirect()
                ->route('portfolio.upload')
                ->with('success', 'Portfolio submitted successfully. Processing has started.');

        } catch (\Throwable $e) {
            Log::error('Manual portfolio submission failed.', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()
                ->withErrors(['client_name' => 'Submission failed. Please try again.'])
                ->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD CSV — generate a CSV string that PortfolioParser can parse.
    | Headers match ALIASES exactly: name, asset_type, current_value.
    |--------------------------------------------------------------------------
    */

    private function buildCsv(array $rows): string
    {
        $lines = ["name,asset_type,current_value"];
        foreach ($rows as $row) {
            $name  = $this->csvCell((string) ($row['name']  ?? ''));
            $type  = $this->csvCell((string) ($row['type']  ?? 'stock'));
            $value = number_format((float) ($row['value'] ?? 0), 2, '.', '');
            $lines[] = "{$name},{$type},{$value}";
        }
        return implode("\r\n", $lines) . "\r\n";
    }

    /** Wrap a cell value in double-quotes and escape internal quotes. */
    private function csvCell(string $value): string
    {
        $escaped = str_replace('"', '""', $value);
        return '"' . $escaped . '"';
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — delete a portfolio file (storage + DB record)
    |--------------------------------------------------------------------------
    */

    private function peekZipClientCount(string $zipPath): int
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return 0;
        }

        $allowed = ['csv', 'xlsx', 'xls', 'pdf'];
        $count   = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || $stat['size'] === 0) {
                continue;
            }
            $name = $stat['name'];
            if (str_ends_with($name, '/')) {
                continue;
            }
            $base = basename($name);
            if (str_starts_with($base, '.') || str_starts_with($base, '__')) {
                continue;
            }
            if (in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), $allowed, true)) {
                $count++;
            }
        }

        $zip->close();
        return $count;
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = Auth::user();

        $file = PortfolioFile::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($file->isProcessing()) {
            return back()->with('error', 'Cannot delete a file that is currently being processed.');
        }

        try {
            if (Storage::disk(self::DISK)->exists($file->path)) {
                Storage::disk(self::DISK)->delete($file->path);
            }

            $file->delete();

            Log::info('Portfolio file deleted by user.', [
                'portfolio_file_id' => $id,
                'user_id'           => $user->id,
            ]);

            return redirect()
                ->route('portfolio.upload')
                ->with('success', 'File "' . $file->original_name . '" deleted.');

        } catch (\Throwable $e) {
            Log::error('Portfolio file deletion failed.', [
                'portfolio_file_id' => $id,
                'user_id'           => $user->id,
                'message'           => $e->getMessage(),
            ]);

            return back()->with('error', 'Deletion failed. Please try again.');
        }
    }
}
