<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Services\ReportHeadline;
use App\Services\RiskEngine\PortfolioRiskCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Risk flags are printed on the report as sentences, with the calculator's
 * own thresholds, never as the constants the code uses.
 */
class ReportFlagWordingTest extends TestCase
{
    private const WORDING = [
        'HIGH_CONCENTRATION' => 'Value is concentrated in a few holdings (concentration index above 60 of 100)',
        'MODERATE_CONCENTRATION' => 'Value is moderately concentrated (concentration index above 30 of 100)',
        'EQUITY_HEAVY' => 'Equity makes up more than 90% of the portfolio',
        'UNDERWEIGHTED_EQUITY' => 'Equity makes up less than 10% of the portfolio',
        'SIGNIFICANT_DRAWDOWN' => 'Holdings with a known cost are more than 15% below what was paid',
        'MODERATE_DRAWDOWN' => 'Holdings with a known cost are more than 7% below what was paid',
        'LOW_DIVERSIFICATION' => 'Fewer than 4 holdings',
        'OVER_DIVERSIFICATION' => 'More than 25 holdings',
        'ELEVATED_OVERALL_RISK' => 'Overall risk score is above 75 of 100',
        'NO_HOLDINGS' => 'No holdings',
    ];

    /** Words that would turn a description into advice. */
    private const PRESCRIPTIVE = ['should', 'consider', 'recommend', 'reduce', 'increase', 'rebalance', 'action'];

    /** Every flag constant that appears in the calculator's source. */
    private function flagsTheCalculatorCanEmit(): array
    {
        $source = file_get_contents((new \ReflectionClass(PortfolioRiskCalculator::class))->getFileName());
        preg_match_all("/\\\$flags\[\] = '([A-Z_]+)'|'risk_flags' => \['([A-Z_]+)'\]/", $source, $matches);

        return array_values(array_unique(array_filter(array_merge($matches[1], $matches[2]))));
    }

    private function flagsSection(array $flags): string
    {
        $html = view('reports.risk-report', [
            'portfolio' => null,
            'riskScore' => new RiskScore(['score' => 45.0, 'volatility' => 1, 'drawdown' => 1, 'meta' => ['risk_flags' => $flags]]),
            'assets' => new Collection([new PortfolioAsset(['name' => 'Example Fund', 'asset_type' => 'mutual_fund', 'quantity' => 1, 'current_value' => 100, 'invested_value' => 100, 'risk_score' => 45, 'risk_level' => 'MEDIUM', 'meta' => []])]),
            'file' => new PortfolioFile(['original_name' => 'h.csv']),
        ])->render();

        preg_match_all('/<div class="flag-row">(.*?)<\/div>/s', $html, $rows);

        return html_entity_decode(implode("\n", array_map('trim', $rows[1])));
    }

    public function test_every_flag_the_calculator_can_emit_has_wording(): void
    {
        $emitted = $this->flagsTheCalculatorCanEmit();

        $this->assertCount(10, $emitted);
        $this->assertEqualsCanonicalizing(array_keys(self::WORDING), $emitted);

        foreach ($emitted as $flag) {
            $this->assertSame(self::WORDING[$flag], ReportHeadline::flagLine($flag));
        }
    }

    public function test_the_report_prints_each_flag_as_words_and_none_as_a_raw_constant(): void
    {
        $section = $this->flagsSection(array_keys(self::WORDING));

        foreach (self::WORDING as $flag => $sentence) {
            $this->assertStringContainsString('• '.$sentence, $section);
            $this->assertStringNotContainsString($flag, $section);
        }

        $this->assertDoesNotMatchRegularExpression('/[A-Z]{2,}_[A-Z]{2,}/', $section);
    }

    public function test_the_numbers_in_the_wording_are_the_calculators_own_thresholds(): void
    {
        $this->assertStringContainsString((string) PortfolioRiskCalculator::HIGH_CONCENTRATION_ABOVE, ReportHeadline::flagLine('HIGH_CONCENTRATION'));
        $this->assertStringContainsString((string) PortfolioRiskCalculator::MODERATE_CONCENTRATION_ABOVE, ReportHeadline::flagLine('MODERATE_CONCENTRATION'));
        $this->assertStringContainsString((PortfolioRiskCalculator::EQUITY_HEAVY_ABOVE * 100).'%', ReportHeadline::flagLine('EQUITY_HEAVY'));
        $this->assertStringContainsString((PortfolioRiskCalculator::UNDERWEIGHTED_EQUITY_BELOW * 100).'%', ReportHeadline::flagLine('UNDERWEIGHTED_EQUITY'));
        $this->assertStringContainsString(PortfolioRiskCalculator::SIGNIFICANT_DRAWDOWN_ABOVE.'%', ReportHeadline::flagLine('SIGNIFICANT_DRAWDOWN'));
        $this->assertStringContainsString(PortfolioRiskCalculator::MODERATE_DRAWDOWN_ABOVE.'%', ReportHeadline::flagLine('MODERATE_DRAWDOWN'));
        $this->assertStringContainsString((string) PortfolioRiskCalculator::LOW_DIVERSIFICATION_BELOW, ReportHeadline::flagLine('LOW_DIVERSIFICATION'));
        $this->assertStringContainsString((string) PortfolioRiskCalculator::OVER_DIVERSIFICATION_ABOVE, ReportHeadline::flagLine('OVER_DIVERSIFICATION'));
        $this->assertStringContainsString((string) PortfolioRiskCalculator::ELEVATED_OVERALL_RISK_ABOVE, ReportHeadline::flagLine('ELEVATED_OVERALL_RISK'));
    }

    public function test_an_unknown_flag_is_shown_in_ordinary_words_never_blank(): void
    {
        $this->assertSame('Some new flag', ReportHeadline::flagLine('SOME_NEW_FLAG'));
        $this->assertStringContainsString('• Some new flag', $this->flagsSection(['SOME_NEW_FLAG']));
    }

    public function test_no_flag_wording_or_tile_label_tells_the_reader_what_to_do(): void
    {
        $text = implode("\n", array_map([ReportHeadline::class, 'flagLine'], array_keys(self::WORDING)))
            ."\n".$this->flagsSection(array_keys(self::WORDING))
            ."\nLargest holding\nGain / loss vs cost\non 1 of 3 holdings\ncost not available";

        foreach (self::PRESCRIPTIVE as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text);
        }
    }
}
