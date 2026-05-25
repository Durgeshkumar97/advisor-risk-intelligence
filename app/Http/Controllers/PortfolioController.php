<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $portfolios = Portfolio::where('user_id', Auth::id())
            ->withCount('assets')
            ->withCount('files')
            ->latest()
            ->get();

        return view('portfolio.manage', compact('portfolios'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — create a new portfolio
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:100',
        ]);

        Portfolio::create([
            'user_id'    => Auth::id(),
            'name'       => $validated['name'],
            'total_value' => 0,
            'risk_score' => 0,
            'risk_level' => 'LOW',
        ]);

        return redirect()->route('portfolio.manage')
            ->with('success', 'Portfolio created.');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — rename a portfolio
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, int $id): RedirectResponse
    {
        $portfolio = Portfolio::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:100',
        ]);

        $portfolio->update(['name' => $validated['name']]);

        return redirect()->route('portfolio.manage')
            ->with('success', 'Portfolio renamed.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — delete a portfolio and all its assets / files
    |--------------------------------------------------------------------------
    */

    public function destroy(int $id): RedirectResponse
    {
        $portfolio = Portfolio::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Prevent deleting the last portfolio
        $portfolioCount = Portfolio::where('user_id', Auth::id())->count();

        if ($portfolioCount <= 1) {
            return redirect()->route('portfolio.manage')
                ->with('error', 'You must keep at least one portfolio.');
        }

        $portfolio->delete();

        return redirect()->route('portfolio.manage')
            ->with('success', 'Portfolio deleted.');
    }
}
