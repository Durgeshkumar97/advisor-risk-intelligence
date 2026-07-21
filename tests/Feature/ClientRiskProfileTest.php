<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClientRiskProfile;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

require_once __DIR__ . '/../Support/SharedFixtures.php';

// ─── file-level helpers ───────────────────────────────────────────────────────

function riskProfileAnswers(array $overrides = []): array
{
    return array_merge([
        'time_horizon'      => 4, // 90
        'income_stability'  => 3, // 85
        'drawdown_reaction' => 3, // 80
        'emergency_savings' => 3, // 80
        'primary_goal'      => 4, // 90
        // avg = 85.0
    ], $overrides);
}

describe('advisor fills in the client risk profile for one of their portfolios', function () {

    it('shows a blank edit form for a portfolio with no existing profile', function () {
        $user      = activeSubscriberUser();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

        $response = $this->actingAs($user)->get(route('portfolio.risk-profile.edit', $portfolio->id));

        $response->assertOk();
        $response->assertViewHas('profile', null);
        $response->assertViewHas('portfolio', fn ($p) => $p->id === $portfolio->id);
    });

    it('creates a new client risk profile with the correctly computed capacity score', function () {
        $user      = activeSubscriberUser();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

        $this->actingAs($user)
            ->post(route('portfolio.risk-profile.update', $portfolio->id), riskProfileAnswers())
            ->assertRedirect(route('portfolio.manage'));

        $profile = ClientRiskProfile::where('portfolio_id', $portfolio->id)->first();

        expect($profile)->not->toBeNull();
        expect((float) $profile->capacity_score)->toBe(85.0);
        expect($profile->time_horizon)->toBe(4);
        expect($profile->primary_goal)->toBe(4);
    });

    it('updates the existing profile in place on resubmission, rather than creating a second row', function () {
        $user      = activeSubscriberUser();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

        $this->actingAs($user)->post(
            route('portfolio.risk-profile.update', $portfolio->id),
            riskProfileAnswers(),
        );

        // Resubmit with the most conservative answers (avg = 14.0).
        $this->actingAs($user)->post(
            route('portfolio.risk-profile.update', $portfolio->id),
            riskProfileAnswers([
                'time_horizon'      => 1,
                'income_stability'  => 1,
                'drawdown_reaction' => 1,
                'emergency_savings' => 1,
                'primary_goal'      => 1,
            ]),
        );

        expect(ClientRiskProfile::where('portfolio_id', $portfolio->id)->count())->toBe(1);

        $profile = ClientRiskProfile::where('portfolio_id', $portfolio->id)->first();
        expect((float) $profile->capacity_score)->toBe(14.0);
    });

    it('rejects an out-of-range answer and does not create a profile', function () {
        $user      = activeSubscriberUser();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

        $this->actingAs($user)
            ->post(
                route('portfolio.risk-profile.update', $portfolio->id),
                riskProfileAnswers(['time_horizon' => 5]), // only 1-4 valid
            )
            ->assertSessionHasErrors('time_horizon');

        expect(ClientRiskProfile::where('portfolio_id', $portfolio->id)->exists())->toBeFalse();
    });

    it('does not let an advisor view or edit another advisor\'s portfolio risk profile', function () {
        $owner   = activeSubscriberUser();
        $other   = activeSubscriberUser();
        $theirs  = Portfolio::create(['user_id' => $owner->id, 'name' => 'Owner\'s Client']);

        $this->actingAs($other)
            ->get(route('portfolio.risk-profile.edit', $theirs->id))
            ->assertNotFound();

        $this->actingAs($other)
            ->post(route('portfolio.risk-profile.update', $theirs->id), riskProfileAnswers())
            ->assertNotFound();

        expect(ClientRiskProfile::where('portfolio_id', $theirs->id)->exists())->toBeFalse();
    });
});

describe('the PDF report renders the comparison — and is unchanged when no profile exists', function () {

    function renderReportHtml(Portfolio $portfolio, RiskScore $riskScore, PortfolioFile $file): string
    {
        return view('reports.risk-report', [
            'portfolio' => $portfolio,
            'riskScore' => $riskScore,
            'assets'    => collect(),
            'file'      => $file,
        ])->render();
    }

    it('renders the client risk tolerance comparison when a profile exists', function () {
        $user      = activeSubscriberUser();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

        ClientRiskProfile::create(array_merge(
            ['portfolio_id' => $portfolio->id],
            riskProfileAnswers(['time_horizon' => 2, 'income_stability' => 2, 'drawdown_reaction' => 2, 'emergency_savings' => 2, 'primary_goal' => 2]),
            ['capacity_score' => 47.0],
        ));

        $riskScore = RiskScore::create([
            'user_id'      => $user->id,
            'portfolio_id' => $portfolio->id,
            'score'        => 78.0,
            'volatility'   => 10,
            'drawdown'     => 5,
            'meta'         => [],
            'generated_at' => now(),
        ]);

        $file = PortfolioFile::create([
            'user_id'       => $user->id,
            'portfolio_id'  => $portfolio->id,
            'original_name' => 'client_a.csv',
            'stored_name'   => 'client_a.csv',
            'path'          => 'uploads/client_a.csv',
            'mime_type'     => 'text/csv',
            'file_size'     => 10,
            'status'        => PortfolioFile::STATUS_PROCESSED,
        ]);

        $html = renderReportHtml($portfolio, $riskScore, $file);

        expect($html)->toContain('Client Risk Tolerance Comparison');
        expect($html)->toContain('exceeds client tolerance by 31 points');
    });

    it('renders identically to before this feature existed when the portfolio has no client risk profile (purely additive regression check)', function () {
        $user      = activeSubscriberUser();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client B']);

        expect($portfolio->clientRiskProfile)->toBeNull();

        $riskScore = RiskScore::create([
            'user_id'      => $user->id,
            'portfolio_id' => $portfolio->id,
            'score'        => 78.0,
            'volatility'   => 10,
            'drawdown'     => 5,
            'meta'         => [],
            'generated_at' => now(),
        ]);

        $file = PortfolioFile::create([
            'user_id'       => $user->id,
            'portfolio_id'  => $portfolio->id,
            'original_name' => 'client_b.csv',
            'stored_name'   => 'client_b.csv',
            'path'          => 'uploads/client_b.csv',
            'mime_type'     => 'text/csv',
            'file_size'     => 10,
            'status'        => PortfolioFile::STATUS_PROCESSED,
        ]);

        $html = renderReportHtml($portfolio, $riskScore, $file);

        expect($html)->not->toContain('Client Risk Tolerance Comparison');
        expect($html)->not->toContain('well-aligned');
        expect($html)->not->toContain('exceeds client tolerance');
        expect($html)->not->toContain('below client tolerance');
        // The rest of the report still renders correctly (no error, real content).
        expect($html)->toContain('Risk Score');
        expect($html)->toContain('78');
    });
});
