<?php

namespace App\Http\Controllers;

use App\Exceptions\PortfolioUploadException;
use App\Http\Requests\StorePortfolioUploadRequest;
use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\Subscription;
use App\Services\PortfolioUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PortfolioUploadController extends Controller
{
    private const DISK = 'portfolios';

    /*
    |--------------------------------------------------------------------------
    | ZIP-BOMB CAPS
    |--------------------------------------------------------------------------
    |
    | The highest plan (Team) allows 1,000 clients/month — the most a
    | legitimate single-ZIP batch upload could plausibly need. 2,000 gives a
    | 2x margin above that, while sitting orders of magnitude below the
    | entry counts a genuine entry-count zip bomb would use.
    |
    | 500MB uncompressed is ~25x the 20MB compressed upload cap — generous
    | headroom for real office documents (which rarely exceed 5:1-10:1
    | compression), while sitting far below actual zip-bomb decompression
    | ratios (DEFLATE's theoretical max is ~1032:1 for a single stream).
    |
    */

    private const MAX_ZIP_ENTRIES = 2000;

    private const MAX_ZIP_UNCOMPRESSED_BYTES = 500 * 1024 * 1024;

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

        if (! $subscription || (! $subscription->isActive() && ! $subscription->isTrial() && ! $subscription->isInGracePeriod())) {
            return redirect()->route('pricing')
                ->with('error', 'An active subscription is required to upload portfolios.');
        }

        $plan = $subscription->plan;
        $monthlyClientLimit = $plan->monthly_client_limit ?? 50;
        $monthlyClientCount = PortfolioFile::monthlyClientCount($user->id);
        $monthlyResetDate = now()->addMonthNoOverflow()->startOfMonth()->format('d M Y');

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
            'portfolios' => $portfolios,
            'files' => $files,
            'monthlyClientCount' => $monthlyClientCount,
            'monthlyClientLimit' => $monthlyClientLimit,
            'monthlyResetDate' => $monthlyResetDate,
            'planName' => $plan->name ?? 'Unknown',
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

        if (! $subscription || (! $subscription->isActive() && ! $subscription->isTrial() && ! $subscription->isInGracePeriod())) {
            return redirect()->route('pricing')
                ->with('error', 'An active subscription is required to upload portfolios.');
        }

        // Monthly client limit check.
        // NOTE: race window — concurrent uploads from the same user could both pass this check simultaneously.
        $limit = $subscription->plan?->monthly_client_limit ?? 50;
        $currentCount = PortfolioFile::monthlyClientCount($user->id);
        $resetDate = now()->addMonthNoOverflow()->startOfMonth()->format('d M Y');

        if (strtolower($request->getFile()->getClientOriginalExtension()) === 'zip') {
            $zipMeta = $this->peekZipMetadata($request->getFile()->getRealPath());

            if ($zipMeta['entry_count'] > self::MAX_ZIP_ENTRIES) {
                return back()
                    ->withErrors(['file' => 'This ZIP contains too many files ('.number_format($zipMeta['entry_count']).'). Maximum allowed is '.number_format(self::MAX_ZIP_ENTRIES).' — please split it into smaller batches.'])
                    ->withInput();
            }

            if ($zipMeta['total_uncompressed_size'] !== null && $zipMeta['total_uncompressed_size'] > self::MAX_ZIP_UNCOMPRESSED_BYTES) {
                return back()
                    ->withErrors(['file' => 'This ZIP is too large once uncompressed (over '.number_format(self::MAX_ZIP_UNCOMPRESSED_BYTES / 1048576).'MB). Please split it into smaller batches.'])
                    ->withInput();
            }

            $peekCount = $zipMeta['client_count'];
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
                userId: $user->id,
                file: $request->getFile(),
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
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
            ]);

            return back()
                ->withErrors(['file' => 'Upload failed. Please try again.'])
                ->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — delete a portfolio file (storage + DB record)
    |--------------------------------------------------------------------------
    */

    /**
     * Inspect a ZIP's metadata WITHOUT extracting it — entry count and total
     * uncompressed size come straight from ZipArchive::statIndex(), which
     * reads the central directory only, so this is safe to run on an
     * untrusted upload before committing any disk/CPU work to it.
     *
     * @return array{opened: bool, entry_count: int, total_uncompressed_size: ?int, client_count: int}
     *                                                                                                 total_uncompressed_size is null when entry_count already exceeded
     *                                                                                                 MAX_ZIP_ENTRIES — rejected before the summing loop even starts.
     */
    private function peekZipMetadata(string $zipPath): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return ['opened' => false, 'entry_count' => 0, 'total_uncompressed_size' => 0, 'client_count' => 0];
        }

        $entryCount = $zip->numFiles;

        // Entry count is checked first — O(1), no per-entry work at all — so
        // a zip crafted with a huge number of tiny entries never reaches the
        // size-summing loop below.
        if ($entryCount > self::MAX_ZIP_ENTRIES) {
            $zip->close();

            return ['opened' => true, 'entry_count' => $entryCount, 'total_uncompressed_size' => null, 'client_count' => 0];
        }

        $allowed = ['csv', 'xlsx', 'xls', 'pdf'];
        $clientCount = 0;
        $totalSize = 0;

        for ($i = 0; $i < $entryCount; $i++) {
            $stat = $zip->statIndex($i);

            if (! $stat) {
                continue;
            }

            $totalSize += $stat['size'];

            // Early exit the moment the cap is breached — bounds worst-case
            // CPU work even when many entries each claim a large size.
            if ($totalSize > self::MAX_ZIP_UNCOMPRESSED_BYTES) {
                $zip->close();

                return ['opened' => true, 'entry_count' => $entryCount, 'total_uncompressed_size' => $totalSize, 'client_count' => $clientCount];
            }

            if ($stat['size'] === 0) {
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
                $clientCount++;
            }
        }

        $zip->close();

        return ['opened' => true, 'entry_count' => $entryCount, 'total_uncompressed_size' => $totalSize, 'client_count' => $clientCount];
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = Auth::user();

        $file = PortfolioFile::findOrFail($id);
        $this->authorize('delete', $file);

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
                'user_id' => $user->id,
            ]);

            return redirect()
                ->route('portfolio.upload')
                ->with('success', 'File "'.$file->original_name.'" deleted.');

        } catch (\Throwable $e) {
            Log::error('Portfolio file deletion failed.', [
                'portfolio_file_id' => $id,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'Deletion failed. Please try again.');
        }
    }
}
