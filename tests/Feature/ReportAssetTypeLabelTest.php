<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** The holdings table names each asset type in words, not as the stored key. */
class ReportAssetTypeLabelTest extends TestCase
{
    private const LABELS = [
        'stock' => 'Stock',
        'mutual_fund' => 'Mutual Fund',
        'etf' => 'ETF',
        'bond' => 'Bond',
        'commodity' => 'Commodity',
        'foreign_stock' => 'Foreign Stock',
        'crypto' => 'Crypto',
        'cash' => 'Cash',
    ];

    public function test_every_asset_type_the_parser_produces_has_a_label_in_words(): void
    {
        // The parser's own list of types, so a new one cannot be added without a label.
        $types = array_keys((new \ReflectionClassConstant(\App\Services\RiskEngine\PortfolioParser::class, 'ASSET_TYPE_MAP'))->getValue());

        $this->assertEqualsCanonicalizing(array_keys(self::LABELS), $types);

        foreach (self::LABELS as $type => $label) {
            $this->assertSame($label, (new PortfolioAsset(['asset_type' => $type]))->formattedAssetType());
        }
    }

    public function test_the_holdings_table_prints_the_label_and_never_the_stored_key(): void
    {
        $assets = [];
        foreach (array_keys(self::LABELS) as $i => $type) {
            $assets[] = new PortfolioAsset(['name' => 'Holding '.$i, 'asset_type' => $type, 'quantity' => 1, 'current_value' => 1000, 'invested_value' => 1000, 'profit_loss' => 0, 'risk_score' => 45, 'risk_level' => 'MEDIUM', 'meta' => []]);
        }

        $html = view('reports.risk-report', [
            'portfolio' => null,
            'riskScore' => new RiskScore(['score' => 45.0, 'volatility' => 1, 'drawdown' => 1, 'meta' => []]),
            'assets' => new Collection($assets),
            'file' => new PortfolioFile(['original_name' => 'h.csv']),
        ])->render();

        $start = strpos($html, '<table class="assets-table">');
        $table = substr($html, $start, strpos($html, '</table>', $start) - $start);

        foreach (self::LABELS as $type => $label) {
            $this->assertStringContainsString('<td style="color:#64748b;">'.$label.'</td>', $table);
        }

        $this->assertStringNotContainsString('mutual_fund', $table);
        $this->assertStringNotContainsString('foreign_stock', $table);
        $this->assertStringNotContainsString('>Etf<', $table);
        $this->assertDoesNotMatchRegularExpression('/<td style="color:#64748b;">[a-z_]+<\/td>/', $table);
    }
}
