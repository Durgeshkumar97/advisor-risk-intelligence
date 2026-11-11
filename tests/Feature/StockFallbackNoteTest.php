<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Services\RiskEngine\AssetRiskScorer;
use App\Services\StockRiskService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * When stocks fall back to the flat stock score, the report says so, and says
 * why truthfully:
 *  - no classifier configured (RISK_SERVICE_URL empty): stock-by-stock
 *    classification is not part of RiskSignal yet;
 *  - classifier configured but the call failed: those stocks could not be
 *    classified this time.
 * The note is never hidden.
 */
class StockFallbackNoteTest extends TestCase
{
    private function reportHtml(int $fallbackCount): string
    {
        $stock = new PortfolioAsset([
            'name' => 'Example Motors', 'asset_type' => 'stock', 'quantity' => 1,
            'current_value' => 1000, 'invested_value' => 1000, 'profit_loss' => 0,
            'risk_score' => 65, 'risk_level' => 'MEDIUM',
            'meta' => ['stock_risk' => ['source' => AssetRiskScorer::SOURCE_FALLBACK_UNAVAILABLE]],
        ]);

        return view('reports.risk-report', [
            'portfolio' => null,
            'riskScore' => new RiskScore(['score' => 45.0, 'volatility' => 1, 'drawdown' => 1,
                'meta' => ['stock_risk_fallback_count' => $fallbackCount]]),
            'assets' => new Collection([$stock]),
            'file' => new PortfolioFile(['original_name' => 'h.csv']),
        ])->render();
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
    }

    public function test_not_configured_says_stock_classification_is_not_yet_part_of_risksignal(): void
    {
        config(['services.risk_service.url' => '']);

        $text = $this->text($this->reportHtml(3));

        $this->assertStringContainsString(
            'Note: 3 stocks are scored by asset type (stock = 65), not individually. Stock-by-stock classification is not yet part of RiskSignal.',
            $text,
        );
        $this->assertStringNotContainsString('this time', $text);
    }

    public function test_configured_but_failed_says_the_stocks_could_not_be_classified_this_time(): void
    {
        config(['services.risk_service.url' => 'https://risk-service.test']);

        $text = $this->text($this->reportHtml(3));

        $this->assertStringContainsString(
            'Note: 3 stocks could not be classified individually this time and were scored by asset type (stock = 65).',
            $text,
        );
        $this->assertStringNotContainsString('not yet part of RiskSignal', $text);
    }

    public function test_one_stock_reads_in_the_singular(): void
    {
        config(['services.risk_service.url' => 'https://risk-service.test']);
        $this->assertStringContainsString(
            'Note: 1 stock could not be classified individually this time and was scored by asset type (stock = 65).',
            $this->text($this->reportHtml(1)),
        );

        config(['services.risk_service.url' => '']);
        $this->assertStringContainsString(
            'Note: 1 stock is scored by asset type (stock = 65), not individually.',
            $this->text($this->reportHtml(1)),
        );
    }

    public function test_no_note_when_no_stock_fell_back(): void
    {
        config(['services.risk_service.url' => 'https://risk-service.test']);

        $text = $this->text($this->reportHtml(0));

        $this->assertStringNotContainsString('scored by asset type (stock', $text);
    }

    public function test_an_unconfigured_classifier_makes_no_http_call_and_every_stock_falls_back(): void
    {
        config(['services.risk_service.url' => '']);
        Http::fake();

        $results = (new StockRiskService)->classifyBatch(['RELIANCE', 'INFY']);

        Http::assertNothingSent();
        $this->assertTrue($results['RELIANCE']['unavailable']);
        $this->assertTrue($results['INFY']['unavailable']);
        $this->assertFalse(StockRiskService::isConfigured());
    }

    public function test_the_shipped_default_url_is_empty_so_unconfigured_is_distinguishable_from_down(): void
    {
        // Read the config file itself with no RISK_SERVICE_URL in the
        // environment: the default must not point at a host.
        $previous = getenv('RISK_SERVICE_URL');
        putenv('RISK_SERVICE_URL');
        unset($_ENV['RISK_SERVICE_URL'], $_SERVER['RISK_SERVICE_URL']);

        try {
            $services = require base_path('config/services.php');
            $this->assertSame('', $services['risk_service']['url']);
        } finally {
            if ($previous !== false) {
                putenv("RISK_SERVICE_URL={$previous}");
            }
        }
    }
}
