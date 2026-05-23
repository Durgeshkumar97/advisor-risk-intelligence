<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePortfolioUploadRequest;
use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\Subscription;
use App\Services\PortfolioUploadException;
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

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

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

        /*
        |--------------------------------------------------------------------------
        | SUBSCRIPTION GUARD
        |--------------------------------------------------------------------------
        */

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription || (!$subscription->isActive() && !$subscription->isTrial() && !$subscription->isInGracePeriod())) {
            return redirect()->route('pricing')
                ->with('error', 'An active subscription is required to upload portfolios.');
        }

        /*
        |--------------------------------------------------------------------------
        | PLAN LIMITS
        |--------------------------------------------------------------------------
        */

        $plan            = $subscription->plan;
        $portfolioLimit  = $plan->portfolio_limit ?? 1;
        $portfolioCount  = Portfolio::where('user_id', $user->id)->count();

        /*
        |--------------------------------------------------------------------------
        | USER PORTFOLIOS & FILES
        |--------------------------------------------------------------------------
        */

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
            'portfolios'     => $portfolios,
            'files'          => $files,
            'portfolioCount' => $portfolioCount,
            'portfolioLimit' => $portfolioLimit,
            'planName'       => $plan->name ?? 'Unknown',
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

        /*
        |--------------------------------------------------------------------------
        | SUBSCRIPTION GUARD (re-check on POST — never trust only the GET guard)
        |--------------------------------------------------------------------------
        */

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$subscription || (!$subscription->isActive() && !$subscription->isTrial() && !$subscription->isInGracePeriod())) {
            return redirect()->route('pricing')
                ->with('error', 'An active subscription is required to upload portfolios.');
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

    public function destroy(int $id): RedirectResponse
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | LOAD + VERIFY OWNERSHIP
        |--------------------------------------------------------------------------
        */

        $file = PortfolioFile::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | BLOCK DELETION WHILE PROCESSING
        |--------------------------------------------------------------------------
        */

        if ($file->isProcessing()) {
            return back()->with('error', 'Cannot delete a file that is currently being processed.');
        }

        try {

            /*
            |------------------------------------------------------------------
            | REMOVE FROM STORAGE (best-effort — don't fail if already gone)
            |------------------------------------------------------------------
            */

            if (Storage::disk(self::DISK)->exists($file->path)) {
                Storage::disk(self::DISK)->delete($file->path);
            }

            /*
            |------------------------------------------------------------------
            | DELETE DB RECORD
            |------------------------------------------------------------------
            */

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
