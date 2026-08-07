<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRiskProfileRequest;
use App\Models\ClientRiskProfile;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PortfolioRiskProfileController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | EDIT — show the client risk-tolerance questionnaire
    |--------------------------------------------------------------------------
    */

    public function edit(int $id): View
    {
        $portfolio = Portfolio::where('user_id', Auth::id())->findOrFail($id);
        $profile = $portfolio->clientRiskProfile;

        return view('portfolio.risk-profile', compact('portfolio', 'profile'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — save (create or replace) the client risk profile
    |--------------------------------------------------------------------------
    */

    public function update(StoreClientRiskProfileRequest $request, int $id): RedirectResponse
    {
        $portfolio = Portfolio::where('user_id', Auth::id())->findOrFail($id);
        $validated = $request->validated();

        $capacityScore = ClientRiskProfile::computeCapacityScore(
            timeHorizon: $validated['time_horizon'],
            incomeStability: $validated['income_stability'],
            drawdownReaction: $validated['drawdown_reaction'],
            emergencySavings: $validated['emergency_savings'],
            primaryGoal: $validated['primary_goal'],
        );

        ClientRiskProfile::updateOrCreate(
            ['portfolio_id' => $portfolio->id],
            [...$validated, 'capacity_score' => $capacityScore],
        );

        return redirect()
            ->route('portfolio.manage')
            ->with('success', 'Client risk profile saved.');
    }
}
