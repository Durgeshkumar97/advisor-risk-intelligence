<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\RiskReportMail;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The report's disclaimer says what the report is and is not. It is written
 * once (resources/views/reports/_disclaimer.blade.php) and included wherever
 * it is shown, so two surfaces can never carry two versions of it.
 */
class ReportDisclaimerTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT = 'RiskSignal is a portfolio analytics tool. This report describes the composition and risk characteristics of the holdings provided. It is not investment advice, a recommendation to buy, sell or hold any security, or a guarantee of future performance. It is prepared for the financial professional who generated it, for use alongside their own judgement. RiskSignal is not registered with SEBI as an Investment Adviser or Research Analyst.';

    private const PROTECTIVE_PHRASES = ['not investment advice', 'recommendation to buy, sell or hold', 'not registered with SEBI'];

    /** Every rendered surface that shows the report disclaimer: name => visible text. */
    private function surfaces(): array
    {
        $pdf = view('reports.risk-report', [
            'portfolio' => null,
            'riskScore' => new RiskScore(['score' => 45.0, 'volatility' => 1, 'drawdown' => 1, 'meta' => []]),
            'assets' => new Collection([new PortfolioAsset(['name' => 'Example Fund', 'asset_type' => 'mutual_fund', 'quantity' => 1, 'current_value' => 100, 'invested_value' => 100, 'risk_score' => 45, 'risk_level' => 'MEDIUM', 'meta' => []])]),
            'file' => new PortfolioFile(['original_name' => 'h.csv']),
        ])->render();

        return [
            'risk report (PDF)' => $this->visibleText($pdf),
            'risk report email' => $this->visibleText($this->reportEmail()),
        ];
    }

    /** The email that carries the PDF, rendered as it is sent. */
    private function reportEmail(): string
    {
        Storage::fake('portfolios');
        Storage::disk('portfolios')->put('reports/report.pdf', '%PDF-test');

        $user = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Client A']);
        $file = PortfolioFile::create([
            'user_id' => $user->id, 'portfolio_id' => $portfolio->id, 'original_name' => 'holdings.csv', 'stored_name' => 'h.csv',
            'path' => 'h.csv', 'mime_type' => 'text/csv', 'file_size' => 1, 'status' => PortfolioFile::STATUS_PROCESSED,
            'report_path' => 'reports/report.pdf',
        ]);
        $riskScore = RiskScore::create(['user_id' => $user->id, 'portfolio_id' => $portfolio->id, 'score' => 45, 'volatility' => 1, 'drawdown' => 1, 'generated_at' => now(), 'meta' => []]);

        return (new RiskReportMail($file, $riskScore))->render();
    }

    private function visibleText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)));
    }

    public function test_every_surface_carries_the_disclaimer_word_for_word(): void
    {
        $surfaces = $this->surfaces();

        $this->assertSame(['risk report (PDF)', 'risk report email'], array_keys($surfaces));

        foreach ($surfaces as $surface => $text) {
            $this->assertStringContainsString(self::TEXT, $text, $surface);
        }
    }

    public function test_the_disclaimer_does_not_point_to_a_section_that_a_report_may_not_have(): void
    {
        // "How This Report Was Built" is printed only when something in it
        // applies, so the disclaimer must not refer the reader to it.
        $this->assertStringNotContainsString('How This Report Was Built', self::TEXT);
        $this->assertStringNotContainsString('How This Report Was Built', file_get_contents(resource_path('views/reports/_disclaimer.blade.php')));
    }

    public function test_every_surface_contains_the_three_protective_phrases_and_none_says_educational(): void
    {
        foreach ($this->surfaces() as $surface => $text) {
            foreach (self::PROTECTIVE_PHRASES as $phrase) {
                $this->assertStringContainsString($phrase, $text, "{$surface}: missing \"{$phrase}\"");
            }

            $this->assertStringNotContainsStringIgnoringCase('educational', $text, $surface);
            // The sentences it replaces.
            $this->assertStringNotContainsString('portfolio management recommendations', $text, $surface);
            $this->assertStringNotContainsString('not registered as an Investment Adviser under SEBI regulations', $text, $surface);
        }
    }

    public function test_the_disclaimer_is_written_in_one_file_only(): void
    {
        $holding = [];

        foreach ((new Finder)->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            if (str_contains(preg_replace('/\s+/', ' ', $file->getContents()), 'recommendation to buy, sell or hold')) {
                $holding[] = $file->getRelativePathname();
            }
        }

        $this->assertSame(['reports/_disclaimer.blade.php'], $holding);
    }
}
