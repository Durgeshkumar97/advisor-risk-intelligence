<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — list all portfolios for the authenticated user
    |--------------------------------------------------------------------------
    */

    public function index(): View
    {
        $portfolios = Portfolio::query()
            ->withCount(['files', 'assets'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('portfolio.manage', compact('portfolios'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — create a new named portfolio
    |--------------------------------------------------------------------------
    |
    | Enforces a per-user portfolio cap to prevent unlimited resource creation.
    |
    */

    public function store(Request $request): RedirectResponse
    {
        /*
        |--------------------------------------------------------------------------
        | 1. VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2. PER-USER CAP — prevent unbounded portfolio creation
        |--------------------------------------------------------------------------
        */

        $count = Portfolio::where('user_id', Auth::id())->count();

        if ($count >= 20) {
            return redirect()
                ->route('portfolio.manage')
                ->with('error', 'You have reached the maximum of 20 portfolios.');
        }

        /*
        |--------------------------------------------------------------------------
        | 3. CREATE
        |--------------------------------------------------------------------------
        */

        Portfolio::create([
            'user_id' => Auth::id(),
            'name'    => $validated['name'],
        ]);

        return redirect()
            ->route('portfolio.manage')
            ->with('success', 'Portfolio created.');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — rename a portfolio (must belong to the requesting user)
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, int $id): RedirectResponse
    {
        $portfolio = Portfolio::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $portfolio->update(['name' => $validated['name']]);

        return redirect()
            ->route('portfolio.manage')
            ->with('success', 'Portfolio renamed.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — delete a portfolio and all its physical files
    |--------------------------------------------------------------------------
    |
    | Files are deleted from storage first, then the DB record is removed.
    | The DB cascade handles portfolio_files rows automatically.
    |
    | Wrapped in a transaction + try/catch so a storage failure does not
    | leave the DB record orphaned (or vice versa).
    |
    */

    public function destroy(int $id): RedirectResponse
    {
        $portfolio = Portfolio::query()
            ->where('user_id', Auth::id())
            ->with('files')          // eager-load to avoid N+1 in the loop
            ->findOrFail($id);

        try {

            DB::transaction(function () use ($portfolio) {

                /*
                |----------------------------------------------------------------------
                | Delete physical files first.
                | If Storage::delete() fails we throw so the transaction rolls back
                | and the DB record is preserved — no orphaned rows.
                |----------------------------------------------------------------------
                */
                foreach ($portfolio->files as $file) {

                    if (! Storage::disk('portfolios')->delete($file->path)) {

                        Log::error('Portfolio destroy: failed to delete storage file', [
                            'portfolio_id' => $portfolio->id,
                            'file_id'      => $file->id,
                            'path'         => $file->path,
                            'user_id'      => Auth::id(),
                        ]);

                        throw new \RuntimeException(
                            "Could not delete file [{$file->path}] from storage."
                        );
                    }
                }

                /*
                |----------------------------------------------------------------------
                | Delete the DB record — cascades to portfolio_files via constraint.
                | Also handle assets if they have physical storage.
                |----------------------------------------------------------------------
                */
                $portfolio->delete();
            });
        } catch (\Throwable $e) {

            Log::error('Portfolio destroy: failed', [
                'portfolio_id' => $portfolio->id,
                'user_id'      => Auth::id(),
                'message'      => $e->getMessage(),
                'file'         => $e->getFile(),
                'line'         => $e->getLine(),
            ]);

            return redirect()
                ->route('portfolio.manage')
                ->with('error', 'Could not delete portfolio. Please try again.');
        }

        return redirect()
            ->route('portfolio.manage')
            ->with('success', 'Portfolio deleted.');
    }
}
