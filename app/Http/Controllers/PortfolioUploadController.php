<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePortfolioUploadRequest;
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
