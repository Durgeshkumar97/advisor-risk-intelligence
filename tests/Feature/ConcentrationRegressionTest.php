<?php

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Audit finding D2-01: a portfolio that is 100% one fund used to score 0
 * concentration (normalised HHI with n = 1). Through the real upload job it
 * must now score 100 and carry HIGH_CONCENTRATION. Value invented.
 */
class ConcentrationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['amfi.min_rows' => 5]);
        $this->travelTo(Carbon::parse('2026-10-24 06:30', 'Asia/Kolkata'));
        $this->artisan('amfi:sync', ['--source' => base_path('tests/Fixtures/amfi/NAVAll.txt')])->assertSuccessful();
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: string}>  $holdings  name, type, isin
     * @return array{0: \Illuminate\Support\Collection, 1: RiskScore}
     */
    private function process(array $holdings): array
    {
        $csv = "name,asset_type,isin,current_value\n";
        foreach ($holdings as $h) {
            $csv .= "{$h[0]},{$h[1]},".($h[2] ?? '').",10000\n";
        }

        Storage::fake('portfolios');
        Mail::fake();

        $user = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Test Portfolio']);
        Storage::disk('portfolios')->put('uploads/portfolio.csv', $csv);

        ProcessPortfolioFile::dispatchSync(PortfolioFile::create([
            'user_id' => $user->id,
            'portfolio_id' => $portfolio->id,
            'original_name' => 'portfolio.csv',
            'stored_name' => 'portfolio.csv',
            'path' => 'uploads/portfolio.csv',
            'mime_type' => 'text/csv',
            'file_size' => strlen($csv),
            'status' => PortfolioFile::STATUS_PENDING,
        ]));

        return [
            PortfolioAsset::where('portfolio_id', $portfolio->id)->orderBy('id')->get(),
            RiskScore::where('portfolio_id', $portfolio->id)->firstOrFail(),
        ];
    }

    public function test_a_portfolio_that_is_all_one_small_cap_fund_is_fully_concentrated(): void
    {
        [$assets, $score] = $this->process([
            ['Nippon India Small Cap Fund - Direct Growth', 'mutual_fund'],
        ]);

        $this->assertCount(1, $assets);
        $this->assertSame(100.0, (float) $score->meta['concentration_score']);
        $this->assertSame(100.0, (float) $score->meta['top_holding_pct']);
        $this->assertContains('HIGH_CONCENTRATION', $score->meta['risk_flags']);
    }
}
