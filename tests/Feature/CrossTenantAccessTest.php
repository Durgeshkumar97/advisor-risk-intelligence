<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../Support/SharedFixtures.php';

/**
 * Cross-tenant (IDOR) regression tests for every ownership-scoped route.
 *
 * The authorization is correct today — PortfolioController scopes its queries
 * with where('user_id', Auth::id()), and FileController /
 * PortfolioUploadController delegate to PortfolioPolicy / PortfolioFilePolicy.
 * Nothing, however, prevented a refactor from dropping one of those checks
 * without a single test failing. This file is that safety net.
 *
 * EVERY case asserts BOTH halves:
 *
 *   1. the owner CAN perform the action, and
 *   2. a different advisor gets 404 and the resource is unchanged.
 *
 * The owner half is not decoration. Without it a 404 assertion passes for the
 * wrong reason — a missing report file, an absent DB row, a typo'd route
 * parameter — and the test would keep passing after the authorization check
 * was removed. The control proves the 404 is authorization and nothing else.
 *
 * 404 rather than 403 is the deliberate house style: PortfolioFilePolicy uses
 * Response::denyWithStatus(404) and the controllers use findOrFail() on an
 * already-scoped query, so a probing advisor cannot distinguish "exists but
 * not yours" from "does not exist" and cannot enumerate IDs.
 */
class CrossTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('portfolios');
    }

    // ─── helpers ──────────────────────────────────────────────────────────

    /**
     * Two unrelated advisors, each with an active subscription so the 'paid'
     * and 'active.sub' middleware are satisfied and cannot be what produces
     * the 404 we are asserting on.
     *
     * @return array{0: User, 1: User}
     */
    private function twoAdvisors(): array
    {
        return [activeSubscriberUser(), activeSubscriberUser()];
    }

    private function portfolioFor(User $user): Portfolio
    {
        return Portfolio::create([
            'user_id' => $user->id,
            'name' => 'Client of '.$user->id,
        ]);
    }

    /** A processed PortfolioFile with source, report and bundle all on disk. */
    private function processedFileFor(User $user, Portfolio $portfolio): PortfolioFile
    {
        $source = "uploads/{$user->id}/portfolio.csv";
        $report = "reports/{$user->id}/report.pdf";
        $bundle = "reports/{$user->id}/bundle.zip";

        Storage::disk('portfolios')->put($source, 'name,asset_type,current_value');
        Storage::disk('portfolios')->put($report, '%PDF-1.4 fake');
        Storage::disk('portfolios')->put($bundle, 'PK fake-zip');

        return PortfolioFile::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio->id,
            'original_name' => 'portfolio.csv',
            'stored_name' => 'portfolio.csv',
            'path' => $source,
            'report_path' => $report,
            'bundle_report_path' => $bundle,
            'mime_type' => 'text/csv',
            'file_size' => 42,
            'status' => PortfolioFile::STATUS_PROCESSED,
        ]);
    }

    // ─── FileController: the four read/download routes ────────────────────

    /**
     * @return array<string, array{0: string}>
     */
    public static function fileRouteProvider(): array
    {
        return [
            'file.view (GET /file/{id})' => ['file.view'],
            'report.view (GET /report/{id})' => ['report.view'],
            'report.download (GET /report/{id}/download)' => ['report.download'],
            'file.bundle.download (GET /report/{id}/bundle)' => ['file.bundle.download'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fileRouteProvider')]
    public function test_file_routes_are_scoped_to_the_owning_advisor(string $routeName): void
    {
        [$owner, $other] = $this->twoAdvisors();
        $file = $this->processedFileFor($owner, $this->portfolioFor($owner));

        // Control: the owner can reach it, so a 404 below cannot be a missing
        // file, a bad route parameter, or a subscription gate.
        $this->actingAs($owner)
            ->get(route($routeName, $file->id))
            ->assertOk();

        // The actual assertion.
        $this->actingAs($other)
            ->get(route($routeName, $file->id))
            ->assertNotFound();
    }

    // ─── PortfolioController: update / destroy ────────────────────────────

    public function test_portfolio_update_is_scoped_to_the_owning_advisor(): void
    {
        [$owner, $other] = $this->twoAdvisors();
        $portfolio = $this->portfolioFor($owner);

        $this->actingAs($other)
            ->patch(route('portfolio.update', $portfolio->id), ['name' => 'Renamed By Attacker'])
            ->assertNotFound();

        $this->assertSame(
            'Client of '.$owner->id,
            $portfolio->fresh()->name,
            'A non-owner must not be able to rename another advisor\'s portfolio.',
        );

        // Control: the same request from the owner succeeds.
        $this->actingAs($owner)
            ->patch(route('portfolio.update', $portfolio->id), ['name' => 'Renamed By Owner'])
            ->assertRedirect(route('portfolio.manage'));

        $this->assertSame('Renamed By Owner', $portfolio->fresh()->name);
    }

    public function test_portfolio_destroy_is_scoped_to_the_owning_advisor(): void
    {
        [$owner, $other] = $this->twoAdvisors();
        $portfolio = $this->portfolioFor($owner);

        $this->actingAs($other)
            ->delete(route('portfolio.destroy', $portfolio->id))
            ->assertNotFound();

        $this->assertNotNull(
            $portfolio->fresh(),
            'A non-owner must not be able to delete another advisor\'s portfolio.',
        );

        // Control: the owner can delete it.
        $this->actingAs($owner)
            ->delete(route('portfolio.destroy', $portfolio->id))
            ->assertRedirect(route('portfolio.manage'));

        $this->assertNull($portfolio->fresh());
    }

    // ─── PortfolioUploadController: file destroy ──────────────────────────

    public function test_portfolio_file_destroy_is_scoped_to_the_owning_advisor(): void
    {
        [$owner, $other] = $this->twoAdvisors();
        $file = $this->processedFileFor($owner, $this->portfolioFor($owner));

        $this->actingAs($other)
            ->delete(route('portfolio.file.destroy', $file->id))
            ->assertNotFound();

        $this->assertNotNull(
            $file->fresh(),
            'A non-owner must not be able to delete another advisor\'s uploaded file.',
        );
        $this->assertTrue(
            Storage::disk('portfolios')->exists($file->path),
            'A non-owner\'s delete attempt must not remove the file from storage.',
        );

        // Control: the owner can delete it.
        $this->actingAs($owner)
            ->delete(route('portfolio.file.destroy', $file->id))
            ->assertRedirect(route('portfolio.upload'));

        $this->assertNull($file->fresh());
    }

    // ─── Portfolio risk profile ───────────────────────────────────────────
    //
    // Covered by ClientRiskProfileTest, which predates this file. Asserted
    // here too so this file is the single place to check that every
    // ownership-scoped route has cross-tenant coverage.

    public function test_portfolio_risk_profile_is_scoped_to_the_owning_advisor(): void
    {
        [$owner, $other] = $this->twoAdvisors();
        $portfolio = $this->portfolioFor($owner);

        $this->actingAs($other)
            ->get(route('portfolio.risk-profile.edit', $portfolio->id))
            ->assertNotFound();

        // Control: the owner can open the questionnaire.
        $this->actingAs($owner)
            ->get(route('portfolio.risk-profile.edit', $portfolio->id))
            ->assertOk();
    }
}
