<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The remaining places a reader meets these terms: the onboarding page, and
 * the report's market box — where "volatility" and "drawdown" describe the
 * market and are correct, provided the box says which day's market it is.
 */
class ReportLabelSurfacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_describes_the_risk_score_as_a_risk_score(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('onboarding'))
            ->assertOk()
            ->assertSee('Portfolio risk score 0–100')
            ->assertDontSee('volatility score');
    }

    public function test_the_market_box_keeps_its_market_terms_and_states_the_date_of_the_snapshot(): void
    {
        $html = view('reports.risk-report', [
            'portfolio' => null,
            'riskScore' => new RiskScore(['score' => 45.0, 'volatility' => 1, 'drawdown' => 1, 'meta' => ['market_context' => [
                'date' => '2026-08-19', 'score' => 62, 'label' => 'Elevated', 'warning_severity' => 'MEDIUM',
                'warning_text' => 'Market conditions are elevated.', 'vol_regime' => 'HIGH', 'dd_regime' => 'NORMAL', 'market_regime' => 'UPTREND',
            ]]]),
            'assets' => new Collection,
            'file' => new PortfolioFile(['original_name' => 'h.csv']),
        ])->render();

        $start = strpos($html, 'Market Risk Context');
        $box = preg_replace('/[\s\x{A0}]+/u', ' ', html_entity_decode(strip_tags(substr($html, $start, strpos($html, 'Market Score', $start) - $start))));

        // A snapshot weeks old must not read as today's market.
        $this->assertStringContainsString('Market Environment · as of 2026-08-19', $box);
        $this->assertStringContainsString('Volatility: HIGH', $box);
        $this->assertStringContainsString('Drawdown: NORMAL', $box);
    }
}
