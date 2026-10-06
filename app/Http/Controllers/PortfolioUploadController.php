<?php

namespace App\Http\Controllers;

use App\Exceptions\PortfolioUploadException;
use App\Http\Requests\StorePortfolioUploadRequest;
use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\Subscription;
use App\Services\PortfolioUploadService;
use App\Services\ZipClientLayout;
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

            if ($zipMeta['client_count'] > ZipClientLayout::MAX_CLIENTS) {
                return back()
                    ->withErrors(['file' => 'This ZIP has too many clients ('.number_format($zipMeta['client_count']).'). Maximum allowed is '.number_format(ZipClientLayout::MAX_CLIENTS).' per upload — please split it into smaller batches.'])
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

        /*
        |--------------------------------------------------------------------------
        | WHOSE FILE IS IT
        |--------------------------------------------------------------------------
        |
        | A single file names an existing portfolio or a new client (the request
        | has already insisted on exactly one). A typed name that is the same
        | as an existing portfolio's — ignoring case and spacing — means that
        | portfolio; a second portfolio for the same client is never created
        | here. A ZIP names its own clients, so a selection made alongside one
        | is not used, and the advisor is told.
        |
        */

        $portfolioId = $request->getPortfolioId();
        $createdPortfolio = null;
        $notes = [];

        if ($request->isZip()) {
            if ($portfolioId !== null) {
                $notes[] = 'The selected portfolio was not used: a ZIP creates one portfolio per client folder.';
            }

            if ($request->getClientName() !== null) {
                $notes[] = 'The client name was not used: a ZIP names its clients from its folders.';
            }
        } elseif ($request->getClientName() !== null) {
            $existing = $this->portfolioNamed($user->id, $request->getClientName());

            if ($existing !== null) {
                $portfolioId = $existing->id;
                $notes[] = "Added to existing portfolio '{$existing->name}'.";
            } else {
                $createdPortfolio = Portfolio::create([
                    'user_id' => $user->id,
                    'name' => $request->getClientName(),
                ]);
                $portfolioId = $createdPortfolio->id;
            }
        }

        try {
            $portfolioFile = $this->uploadService->handleUpload(
                userId: $user->id,
                file: $request->getFile(),
                portfolioId: $portfolioId,
            );

            // The file is stored: from here the new portfolio has a file in it
            // and stays, whatever happens to the processing job.
            $createdPortfolio = null;

            ProcessPortfolioFile::dispatch($portfolioFile);

            return redirect()
                ->route('portfolio.upload')
                ->with('success', implode(' ', ['Portfolio uploaded successfully. Processing has started.', ...$notes]));

        } catch (PortfolioUploadException $e) {
            $createdPortfolio?->delete();

            Log::warning('Portfolio upload business error.', [
                'message' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return back()
                ->withErrors(['file' => $e->getUserMessage()])
                ->withInput();

        } catch (\Throwable $e) {
            // Do not leave an empty portfolio behind for a file that was never stored.
            $createdPortfolio?->delete();

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

    /**
     * The user's portfolio with this name, comparing names without regard to
     * case or spacing. If several share the name, the most recently updated.
     */
    private function portfolioNamed(int $userId, string $name): ?Portfolio
    {
        $key = Portfolio::nameKey($name);

        return Portfolio::where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Portfolio $portfolio) => Portfolio::nameKey((string) $portfolio->name) === $key);
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
        $clientFolders = [];
        $entries = [];
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

            $name = $stat['name'];
            if (ZipClientLayout::isDirectory($name) || ZipClientLayout::isIgnored($name)) {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'countable' => $stat['size'] > 0
                    && in_array(strtolower(pathinfo(ZipClientLayout::filename($name), PATHINFO_EXTENSION)), $allowed, true),
            ];
        }

        $zip->close();

        // The same container rule the extraction job applies, over the same
        // entries, so the count here matches the clients it will create.
        $wrapper = ZipClientLayout::wrapperFolder(array_column($entries, 'name'));

        foreach ($entries as $entry) {
            if (! $entry['countable']) {
                continue;
            }

            // A top-level folder is one client however many files it holds;
            // a file at the root is one client on its own.
            $folder = ZipClientLayout::folder(ZipClientLayout::unwrap($entry['name'], $wrapper));

            if ($folder === null) {
                $clientCount++;
            } else {
                $clientFolders[ZipClientLayout::clientName($folder)] = true;
            }
        }

        return ['opened' => true, 'entry_count' => $entryCount, 'total_uncompressed_size' => $totalSize, 'client_count' => $clientCount + count($clientFolders)];
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
