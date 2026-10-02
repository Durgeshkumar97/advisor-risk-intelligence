<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\RiskReportMail;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use App\Services\ReportHeadline;
use App\Services\RiskEngine\PortfolioRiskCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Page one of the risk report names what its numbers are. The third and
 * fourth tiles are the largest holding's share and the gain or loss against
 * cost; they used to be labelled "Volatility" and "Max Drawdown", which they
 * were not. All names and figures here are invented.
 */
class ReportPageOneTest extends TestCase
{
    use RefreshDatabase;

    private function asset(string $name, float $current, ?float $invested, array $meta = [], string $type = 'mutual_fund'): PortfolioAsset
    {
        return new PortfolioAsset([
            'name' => $name, 'asset_type' => $type, 'quantity' => 1,
            'current_value' => $current, 'invested_value' => $invested,
            'profit_loss' => $invested === null ? null : $current - $invested,
            'risk_score' => 45, 'risk_level' => 'MEDIUM', 'meta' => $meta,
        ]);
    }

    /** The four tiles of the rendered PDF view, as text. */
    private function tiles(array $assets, array $score = []): string
    {
        $html = view('reports.risk-report', [
            'portfolio' => null,
            // Stored values that used to be printed: deliberately unlike anything the tiles should show.
            'riskScore' => new RiskScore($score + ['score' => 45.0, 'volatility' => 33.33, 'drawdown' => 44.44, 'meta' => []]),
            'assets' => new Collection($assets),
            'file' => new PortfolioFile(['original_name' => 'holdings.csv']),
        ])->render();

        $start = strpos($html, '<table class="summary-table">');

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(substr($html, $start, strpos($html, '</table>', $start) - $start)))));
    }

    private function profitable(): array
    {
        return [
            $this->asset('Example Flexi Cap Fund', 60000, 50000),
            $this->asset('Example Liquid Fund', 25000, 24000),
            $this->asset('Example Gilt Fund', 15000, 14000),
        ];
    }

    // ─── 1. the old labels are gone ──────────────────────────────────────

    public function test_the_report_tiles_no_longer_say_volatility_or_max_drawdown_or_print_those_stored_numbers(): void
    {
        $tiles = $this->tiles($this->profitable());

        $this->assertStringNotContainsString('Volatility', $tiles);
        $this->assertStringNotContainsString('Drawdown', $tiles);
        $this->assertStringNotContainsString('33.33', $tiles);
        $this->assertStringNotContainsString('44.44', $tiles);
        $this->assertStringContainsString('Risk Score 45/100', $tiles);
        $this->assertStringContainsString('Largest holding', $tiles);
        $this->assertStringContainsString('Gain / loss vs cost', $tiles);
    }

    public function test_the_report_email_shows_the_same_two_figures_and_neither_old_label(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);

        foreach ([['Example Flexi Cap Fund', 60000, 50000], ['Example Liquid Fund', 25000, null], ['Example Gilt Fund', 15000, 14000]] as [$name, $current, $invested]) {
            PortfolioAsset::create([
                'portfolio_id' => $portfolio->id, 'name' => $name, 'asset_type' => 'mutual_fund', 'quantity' => 1,
                'buy_price' => 0, 'current_price' => 0, 'current_value' => $current, 'invested_value' => $invested,
                'profit_loss' => $invested === null ? null : $current - $invested, 'risk_score' => 45, 'risk_level' => 'MEDIUM',
            ]);
        }

        $file = PortfolioFile::create([
            'user_id' => $user->id, 'portfolio_id' => $portfolio->id, 'original_name' => 'holdings.csv', 'stored_name' => 'h.csv',
            'path' => 'h.csv', 'mime_type' => 'text/csv', 'file_size' => 1, 'status' => PortfolioFile::STATUS_PROCESSED,
            'report_path' => 'reports/report.pdf',
        ]);
        Storage::fake('portfolios');
        Storage::disk('portfolios')->put('reports/report.pdf', '%PDF-test');
        $riskScore = RiskScore::create(['user_id' => $user->id, 'portfolio_id' => $portfolio->id, 'score' => 45, 'volatility' => 33.33, 'drawdown' => 44.44, 'generated_at' => now(), 'meta' => []]);

        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((new RiskReportMail($file, $riskScore))->render())));

        $this->assertStringNotContainsString('Volatility', $text);
        $this->assertStringNotContainsString('Drawdown', $text);
        $this->assertStringNotContainsString('33.33', $text);
        $this->assertStringNotContainsString('44.44', $text);
        $this->assertStringContainsString('Largest holding 60.0% — Example Flexi Cap Fund', $text);
        // 75,000 now against 64,000 paid, on the two holdings with a cost.
        $this->assertStringContainsString('Gain / loss vs cost +17.2% (on 2 of 3 holdings)', $text);
    }

    // ─── 2. largest holding ──────────────────────────────────────────────

    public function test_the_largest_holding_tile_shows_its_share_of_the_portfolio_and_its_name(): void
    {
        $this->assertStringContainsString('Largest holding 60.0% Example Flexi Cap Fund', $this->tiles($this->profitable()));
        $this->assertSame(['name' => 'Example Flexi Cap Fund', 'share' => 60.0], ReportHeadline::largestHolding(new Collection($this->profitable())));
    }

    public function test_a_single_holding_is_100_percent(): void
    {
        $this->assertStringContainsString('Largest holding 100.0% Example Gilt Fund', $this->tiles([$this->asset('Example Gilt Fund', 15000, 14000)]));
    }

    public function test_a_holding_valued_at_cost_counts_toward_the_largest_holding(): void
    {
        $largest = ReportHeadline::largestHolding(new Collection([
            $this->asset('Example Gilt Fund', 20000, 19000),
            $this->asset('TSTA', 80000, 80000, ['value_basis' => 'cost'], 'foreign_stock'),
        ]));

        $this->assertSame(['name' => 'TSTA', 'share' => 80.0], $largest);
    }

    public function test_a_tie_for_largest_goes_to_the_name_that_sorts_first_and_a_long_name_is_shortened(): void
    {
        $tie = ReportHeadline::largestHolding(new Collection([
            $this->asset('Example Zeta Fund', 50000, 50000),
            $this->asset('Example Alpha Fund', 50000, 50000),
        ]));

        $long = ReportHeadline::largestHolding(new Collection([
            $this->asset('Example Very Long Named Opportunities Fund Direct Plan Growth Option', 1000, 1000),
        ]));

        $this->assertSame('Example Alpha Fund', $tie['name']);
        $this->assertSame('Example Very Long Named Opportunities F…', $long['name']);
        $this->assertSame(40, mb_strlen($long['name']));
    }

    // ─── 3. gain / loss vs cost ──────────────────────────────────────────

    public function test_a_portfolio_in_profit_shows_a_positive_figure_where_the_old_tile_showed_zero(): void
    {
        // 1,00,000 now against 88,000 paid. The stored "drawdown" for this portfolio is 0.00.
        $tiles = $this->tiles($this->profitable(), ['drawdown' => 0.0]);

        $this->assertStringContainsString('Gain / loss vs cost +13.6%', $tiles);
        $this->assertStringNotContainsString('holdings', $tiles);            // every holding counted: no "on N of M"
        $this->assertStringNotContainsString('0.00', $tiles);
    }

    public function test_a_portfolio_in_loss_shows_a_negative_figure(): void
    {
        $tiles = $this->tiles([$this->asset('Example Flexi Cap Fund', 42000, 50000), $this->asset('Example Gilt Fund', 50000, 50000)]);

        $this->assertStringContainsString('Gain / loss vs cost −8.0%', $tiles);     // 92,000 against 1,00,000
    }

    public function test_the_gain_loss_figure_is_coloured_as_a_gain_or_a_loss(): void
    {
        $html = fn (array $assets) => view('reports.risk-report', [
            'portfolio' => null, 'riskScore' => new RiskScore(['score' => 45.0, 'volatility' => 1, 'drawdown' => 1, 'meta' => []]),
            'assets' => new Collection($assets), 'file' => new PortfolioFile(['original_name' => 'h.csv']),
        ])->render();

        $this->assertStringContainsString('<span class="summary-value green">+13.6', $html($this->profitable()));
        $this->assertStringContainsString('<span class="summary-value red">−8.0', $html([$this->asset('Example Flexi Cap Fund', 46000, 50000)]));
    }

    public function test_holdings_without_a_usable_cost_are_left_out_and_the_tile_says_how_many_were_counted(): void
    {
        $tiles = $this->tiles([
            $this->asset('Example Flexi Cap Fund', 55000, 50000),
            $this->asset('Example Depository Holding', 30000, null),                                         // cost unknown
            $this->asset('TSTA', 20000, 20000, ['value_basis' => 'cost'], 'foreign_stock'),                  // valued at cost
        ]);

        // Only the first holding is measured: +10%. Counting TSTA as unmoved money would give +7.1%.
        $this->assertStringContainsString('Gain / loss vs cost +10.0% on 1 of 3 holdings', $tiles);
    }

    public function test_when_no_holding_has_a_cost_the_tile_shows_a_dash_not_a_number(): void
    {
        $tiles = $this->tiles([
            $this->asset('Example Depository Holding', 30000, null),
            $this->asset('TSTA', 20000, 20000, ['value_basis' => 'cost'], 'foreign_stock'),
        ]);

        $this->assertStringContainsString('Gain / loss vs cost — cost not available', $tiles);
        $this->assertStringNotContainsString('vs cost 0', $tiles);
        $this->assertStringNotContainsString('vs cost +', $tiles);
    }

    // ─── 4. the tile and the score measure the same holdings ─────────────

    public function test_the_gain_loss_tile_and_the_calculator_measure_the_same_holdings(): void
    {
        $assets = new Collection([
            $this->asset('Example Flexi Cap Fund', 40000, 50000),
            $this->asset('Example Gilt Fund', 28000, 30000),
            $this->asset('Example Depository Holding', 30000, null),
            $this->asset('TSTA', 20000, 20000, ['value_basis' => 'cost'], 'foreign_stock'),
        ]);

        $tile = ReportHeadline::gainLoss($assets);
        $calculated = (new PortfolioRiskCalculator)->calculate($assets, 1.0);

        // Same cost total, so the same holdings; and in a loss the tile is the
        // calculator's own unrealised-loss figure with its sign.
        $this->assertSame(80000.0, $tile['invested']);
        $this->assertSame($calculated['meta']['total_invested'], $tile['invested']);
        $this->assertSame(-$calculated['drawdown'], round($tile['pct'], 2));
        $this->assertSame(-15.0, round($tile['pct'], 2));
        $this->assertSame([2, 4], [$tile['counted'], $tile['total']]);
    }
}
