<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The PDF report prints numbers the way an Indian advisor reads them, and in
 * one typeface. Values below are invented.
 */
class ReportNumberFormatTest extends TestCase
{
    private function viewData(): array
    {
        return [
            'portfolio' => null,
            'riskScore' => new RiskScore(['score' => 45.0, 'volatility' => 1, 'drawdown' => 1, 'meta' => []]),
            'assets' => new Collection([
                new PortfolioAsset([
                    'name' => 'Example Industries', 'asset_type' => 'stock', 'quantity' => '400.0000',
                    'current_value' => 114000, 'invested_value' => 96000, 'profit_loss' => 18000,
                    'risk_score' => 65, 'risk_level' => 'MEDIUM', 'meta' => [],
                ]),
                new PortfolioAsset([
                    'name' => 'Example Software', 'asset_type' => 'stock', 'quantity' => '12.5000',
                    'current_value' => 72500, 'invested_value' => 80000, 'profit_loss' => -7500,
                    'risk_score' => 65, 'risk_level' => 'MEDIUM', 'meta' => [],
                ]),
            ]),
            'file' => new PortfolioFile(['original_name' => 'h.csv']),
        ];
    }

    /** One holding's row as plain text. */
    private function row(string $html, string $name): string
    {
        $table = strpos($html, 'class="assets-table"');
        $this->assertNotFalse($table, 'No holdings table.');
        $start = strpos($html, $name, $table);
        $this->assertNotFalse($start, "No holdings row for {$name}.");
        $row = substr($html, $start, strpos($html, '</tr>', $start) - $start);

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<br>', ' ', $row)))));
    }

    public function test_money_uses_indian_grouping_and_the_sign_comes_before_the_rupee_symbol(): void
    {
        $html = view('reports.risk-report', $this->viewData())->render();

        $gain = $this->row($html, 'Example Industries');
        $this->assertStringContainsString('₹1,14,000.00', $gain);
        $this->assertStringContainsString('+₹18,000.00', $gain);

        $loss = $this->row($html, 'Example Software');
        $this->assertStringContainsString('₹72,500.00', $loss);
        $this->assertStringContainsString('-₹7,500.00', $loss);
        $this->assertStringNotContainsString('₹-', $loss);
    }

    public function test_quantities_print_without_trailing_zeros(): void
    {
        $html = view('reports.risk-report', $this->viewData())->render();

        $this->assertMatchesRegularExpression('/Stock .* 400 ₹1,14,000\.00/', $this->row($html, 'Example Industries'));
        $this->assertMatchesRegularExpression('/Stock .* 12\.5 ₹72,500\.00/', $this->row($html, 'Example Software'));
    }

    public function test_the_rendered_pdf_uses_dejavu_sans_only_with_no_times_fallback(): void
    {
        $pdf = Pdf::loadView('reports.risk-report', $this->viewData())->output();

        preg_match_all('#/BaseFont\s*/([A-Za-z0-9+\-]+)#', $pdf, $fonts);
        $families = array_unique(array_map(fn ($f) => preg_replace('/^[A-Z]{6}\+/', '', $f), $fonts[1]));

        $this->assertNotEmpty($families);
        foreach ($families as $family) {
            $this->assertStringStartsWith('DejaVuSans', $family, "Unexpected font in the PDF: {$family}");
        }
    }
}
