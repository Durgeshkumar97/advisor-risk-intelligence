<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Services\ReportProvenance;
use App\Services\RiskEngine\AssetRiskScorer;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Every holding's risk score says where it came from. Only an Indian stock
 * with a classification from risk_service is scored from data; a fund's score
 * is its category's default moved by keywords in its name; everything else is
 * the flat default for its asset category. Printed identically, those read as
 * if all had been measured. All names here are invented.
 */
class ScoreProvenanceTest extends TestCase
{
    private const PRESCRIPTIVE = ['should', 'consider', 'recommend', 'reduce', 'increase', 'rebalance', 'action'];

    private function asset(string $name, string $type, array $meta = [], float $score = 45): PortfolioAsset
    {
        return new PortfolioAsset([
            'name' => $name, 'asset_type' => $type, 'quantity' => 1,
            'current_value' => 1000, 'invested_value' => 1000, 'profit_loss' => 0,
            'risk_score' => $score, 'risk_level' => 'MEDIUM', 'meta' => $meta,
        ]);
    }

    private function liveStock(string $name = 'Example Industries'): PortfolioAsset
    {
        return $this->asset($name, 'stock', ['stock_risk' => ['source' => AssetRiskScorer::SOURCE_LIVE, 'confidence' => 0.9]], 50);
    }

    private function fallbackStock(string $name = 'Example Motors'): PortfolioAsset
    {
        return $this->asset($name, 'stock', ['stock_risk' => ['source' => AssetRiskScorer::SOURCE_FALLBACK_UNAVAILABLE]], 65);
    }

    private function html(array $assets): string
    {
        return view('reports.risk-report', [
            'portfolio' => null,
            'riskScore' => new RiskScore(['score' => 45.0, 'volatility' => 1, 'drawdown' => 1, 'meta' => []]),
            'assets' => new Collection($assets),
            'file' => new PortfolioFile(['original_name' => 'h.csv']),
        ])->render();
    }

    /** The risk-score cell of one holding's row, as text. */
    private function scoreCell(string $html, string $name): string
    {
        $start = strpos($html, '<td style="font-weight:600;">'.$name);
        $this->assertNotFalse($start, "No holdings row for {$name}.");

        $row = substr($html, $start, strpos($html, '</tr>', $start) - $start);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cells);

        // name, type, symbol, qty, current value, P&L, RISK SCORE, level
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<br>', ' ', $cells[1][6])))));
    }

    private function builtBox(string $html): string
    {
        $start = strpos($html, 'How This Report Was Built');

        if ($start === false) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(substr($html, $start, strpos($html, '</div>', strpos($html, 'action-box', $start)) - $start)))));
    }

    // ─── 1. stocks ───────────────────────────────────────────────────────

    public function test_a_stock_scored_from_a_classification_says_data_and_one_that_fell_back_says_by_type(): void
    {
        $html = $this->html([$this->liveStock(), $this->fallbackStock()]);

        $this->assertSame('50 data', $this->scoreCell($html, 'Example Industries'));

        // One short word pair on one line, so the label never makes a row taller.
        $this->assertSame(2, substr_count($html, 'white-space:nowrap;">'));
        $this->assertSame('65 by type', $this->scoreCell($html, 'Example Motors'));
    }

    // ─── 2. funds ────────────────────────────────────────────────────────

    public function test_a_fund_whose_name_moved_its_score_says_by_name_and_a_plain_one_says_by_type(): void
    {
        $html = $this->html([
            $this->asset('Example Small Cap Fund', 'mutual_fund', [], 67),
            $this->asset('Example Fund', 'mutual_fund', [], 45),
            $this->asset('Example Nifty 50 ETF', 'etf', [], 30),
        ]);

        $this->assertSame('67 by name', $this->scoreCell($html, 'Example Small Cap Fund'));
        $this->assertSame('45 by type', $this->scoreCell($html, 'Example Fund'));
        $this->assertSame('30 by name', $this->scoreCell($html, 'Example Nifty 50 ETF'));
    }

    // ─── 3. everything else ──────────────────────────────────────────────

    public function test_bonds_cash_and_foreign_stocks_say_by_type_whatever_their_name(): void
    {
        $html = $this->html([
            $this->asset('Example Gilt Bond', 'bond', [], 15),                 // "gilt" is a fund keyword; a bond is not a fund
            $this->asset('Example Liquid Cash', 'cash', [], 5),
            $this->asset('TSTA', 'foreign_stock', ['currency' => 'USD'], 75),
        ]);

        $this->assertSame('15 by type', $this->scoreCell($html, 'Example Gilt Bond'));
        $this->assertSame('5 by type', $this->scoreCell($html, 'Example Liquid Cash'));
        $this->assertSame('75 by type', $this->scoreCell($html, 'TSTA'));
    }

    // ─── 4. the summary line ─────────────────────────────────────────────

    public function test_the_report_counts_how_each_score_was_arrived_at(): void
    {
        $assets = [
            $this->liveStock('Example Industries'), $this->liveStock('Example Steel'),
            $this->asset('Example Small Cap Fund', 'mutual_fund'),
            $this->fallbackStock(), $this->asset('Example Fund', 'mutual_fund'), $this->asset('Example Gilt Bond', 'bond'),
        ];

        $line = "Risk scores: 2 from market data, 1 estimated from the fund's name, 3 by asset category.";

        $this->assertSame($line, ReportProvenance::for(new Collection($assets))['scores']);
        $this->assertStringContainsString($line, $this->builtBox($this->html($assets)));
    }

    public function test_a_count_of_zero_is_left_out_of_the_summary_line(): void
    {
        $noData = ReportProvenance::for(new Collection([$this->asset('Example Small Cap Fund', 'mutual_fund'), $this->asset('Example Gilt Bond', 'bond')]));
        $onlyCategory = ReportProvenance::for(new Collection([$this->asset('Example Gilt Bond', 'bond'), $this->fallbackStock()]));
        $dataAndName = ReportProvenance::for(new Collection([$this->liveStock(), $this->asset('Example Small Cap Fund', 'mutual_fund')]));

        $this->assertSame("Risk scores: 1 estimated from the fund's name, 1 by asset category.", $noData['scores']);
        $this->assertSame('Risk scores: 2 by asset category.', $onlyCategory['scores']);
        $this->assertSame("Risk scores: 1 from market data, 1 estimated from the fund's name.", $dataAndName['scores']);
    }

    public function test_the_summary_line_is_absent_when_every_score_is_data_backed(): void
    {
        $assets = [$this->liveStock('Example Industries'), $this->liveStock('Example Steel')];

        $this->assertNull(ReportProvenance::for(new Collection($assets))['scores']);
        $this->assertStringNotContainsString('Risk scores:', $this->html($assets));
        $this->assertNull(ReportProvenance::for(new Collection)['scores']);
    }

    // ─── 5. the label agrees with the scorer ─────────────────────────────

    public function test_by_name_is_shown_exactly_when_the_name_changed_the_score(): void
    {
        $scorer = new AssetRiskScorer;

        $names = [
            'Example Overnight Fund', 'Example Liquid Fund', 'Example Arbitrage Fund', 'Example Ultra Short Fund',
            'Example Gilt Fund', 'Example Low Duration Fund', 'Example Balanced Fund', 'Example Hybrid Fund',
            'Example Nifty 50 Index Fund', 'Example Bluechip Fund', 'Example Large Cap Fund', 'Example Flexi Cap Fund',
            'Example Focused Fund', 'Example Mid Cap Fund', 'Example Small Cap Fund', 'Example Pharma Fund',
            'Example Technology Fund', 'Example Fund', 'Example Opportunities Fund', 'Example ELSS Tax Saver Fund',
            'Example Growth Plan',
        ];

        $moved = 0;

        foreach (['mutual_fund', 'etf'] as $type) {
            $plain = $scorer->score($type, 'Plain')['score'];

            foreach ($names as $name) {
                $nameChangedTheScore = $scorer->score($type, $name)['score'] !== $plain;
                $moved += (int) $nameChangedTheScore;

                $this->assertSame($nameChangedTheScore, $scorer->nameAdjustsScore($type, $name), "{$type}: {$name}");
                $this->assertSame(
                    $nameChangedTheScore ? 'by name' : 'by type',
                    ReportProvenance::scoreBasisLabel($this->asset($name, $type)),
                    "{$type}: {$name}",
                );
            }
        }

        // Both outcomes were exercised.
        $this->assertSame(34, $moved);

        // Only funds and ETFs are adjusted by name.
        foreach (['stock', 'bond', 'cash', 'commodity', 'foreign_stock', 'crypto', 'something_else'] as $type) {
            $this->assertFalse($scorer->nameAdjustsScore($type, 'Example Small Cap Fund'), $type);
        }
    }

    // ─── 7. wording describes, it does not instruct ──────────────────────

    public function test_the_sublabels_and_summary_line_contain_no_prescriptive_word(): void
    {
        $assets = new Collection([$this->liveStock(), $this->asset('Example Small Cap Fund', 'mutual_fund'), $this->asset('Example Gilt Bond', 'bond')]);

        $text = ReportProvenance::for($assets)['scores']."\n".implode("\n", $assets->map(fn ($a) => ReportProvenance::scoreBasisLabel($a))->all());

        $this->assertStringContainsString('market data', $text);     // the summary line
        $this->assertStringContainsString('by type', $text);         // a sublabel

        foreach (self::PRESCRIPTIVE as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text);
        }
    }
}
