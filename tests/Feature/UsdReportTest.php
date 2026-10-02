<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\FxRate;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use App\Services\RiskEngine\PortfolioParser;
use App\Services\StockRiskService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BrokerExportFixtures as Fixtures;
use Tests\TestCase;

/**
 * The report says what was done to produce it: the exchange rate used, which
 * holdings are carried at cost, and which of a client's files it was built
 * from. Each line appears only when it applies.
 */
class UsdReportTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE = 'Test rate — invented';

    private const HEADING = 'How This Report Was Built';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 10:00:00');
    }

    private function render(array $assets, ?array $clientSources = null): string
    {
        return view('reports.risk-report', [
            'portfolio' => null,
            'riskScore' => new RiskScore(['score' => 45.0, 'volatility' => 18.5, 'drawdown' => 12.0, 'meta' => []]),
            'assets' => new Collection($assets),
            'file' => new PortfolioFile(['original_name' => 'holdings.csv']),
        ] + ($clientSources === null ? [] : ['clientSources' => $clientSources]))->render();
    }

    private function rupeeAsset(): PortfolioAsset
    {
        return new PortfolioAsset([
            'name' => 'Example Flexi Cap Fund', 'asset_type' => 'mutual_fund', 'quantity' => 10,
            'current_value' => 25000, 'invested_value' => 20000, 'profit_loss' => 5000,
            'risk_score' => 45, 'risk_level' => 'MEDIUM',
            'meta' => ['invested_value_source' => 'file'],
        ]);
    }

    private function usdAsset(string $basis = 'cost', string $fxAsOf = '2026-09-29', string $name = 'TSTA'): PortfolioAsset
    {
        return new PortfolioAsset([
            'name' => $name, 'asset_type' => 'foreign_stock', 'quantity' => 2,
            'current_value' => 19197, 'invested_value' => 19197, 'profit_loss' => $basis === 'cost' ? null : 0,
            'risk_score' => 75, 'risk_level' => 'HIGH',
            'meta' => ['currency' => 'USD', 'value_basis' => $basis, 'fx_rate' => 95.985, 'fx_as_of' => $fxAsOf, 'fx_source' => self::SOURCE],
        ]);
    }

    /** The text of the holdings-table row for one holding. */
    private function row(string $html, string $name): string
    {
        $start = strpos($html, '<td style="font-weight:600;">'.$name);
        $this->assertNotFalse($start, "No holdings row for {$name}.");

        $row = str_replace('<br>', ' ', substr($html, $start, strpos($html, '</tr>', $start) - $start));

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($row))));
    }

    /** Everything printed inside the "How This Report Was Built" box, or null when there is no box. */
    private function box(string $html): ?string
    {
        $heading = strpos($html, '<div class="section-heading">'.self::HEADING.'</div>');

        if ($heading === false) {
            return null;
        }

        $start = strpos($html, '<div class="action-box">', $heading);

        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(substr($html, $start, strpos($html, '</div>', $start) - $start)), ENT_QUOTES)));
    }

    // ─── currency line ───────────────────────────────────────────────────

    public function test_the_report_states_the_rate_its_source_and_its_date_when_a_holding_was_converted(): void
    {
        $html = $this->render([$this->rupeeAsset(), $this->usdAsset()]);

        $this->assertStringContainsString(self::HEADING, $html);
        $this->assertStringContainsString('US-dollar holdings converted at ₹95.985 per USD (Test rate — invented, as of 29 Sep 2026).', $html);
        $this->assertStringNotContainsString('days old', $html);       // 2 days old: not stale
    }

    public function test_the_report_says_so_when_the_rate_is_more_than_7_days_old(): void
    {
        $this->assertStringContainsString('This exchange rate is more than 7 days old.', $this->render([$this->usdAsset(fxAsOf: '2026-09-23')]));   // 8 days
        $this->assertStringNotContainsString('days old', $this->render([$this->usdAsset(fxAsOf: '2026-09-24')]));                                  // 7 days
    }

    // ─── valuation line ──────────────────────────────────────────────────

    public function test_the_report_counts_the_holdings_valued_at_cost_and_says_they_are_out_of_the_gain_loss_figure(): void
    {
        $two = $this->render([$this->rupeeAsset(), $this->usdAsset(), $this->usdAsset(name: 'TSTB'), $this->usdAsset('market', name: 'TSTC')]);
        $one = $this->render([$this->usdAsset()]);

        $this->assertStringContainsString('2 holdings are valued at cost — their source export has no current market price — and are excluded from the gain/loss figure.', $two);
        $this->assertStringContainsString('1 holding is valued at cost — its source export has no current market price — and is excluded from the gain/loss figure.', $one);
        $this->assertStringNotContainsString('valued at cost', $this->render([$this->usdAsset('market')]));
    }

    // ─── converted holdings are identifiable in the table ────────────────

    public function test_converted_and_cost_valued_holdings_are_marked_in_the_holdings_table_and_rupee_ones_are_not(): void
    {
        $html = $this->render([$this->rupeeAsset(), $this->usdAsset(), $this->usdAsset('market', name: 'TSTC')]);

        $costRow = $this->row($html, 'TSTA');
        $marketRow = $this->row($html, 'TSTC');
        $rupeeRow = $this->row($html, 'Example Flexi Cap Fund');

        $this->assertStringContainsString('TSTA · USD', $costRow);
        $this->assertStringContainsString('₹19,197.00 at cost', $costRow);
        $this->assertStringContainsString('—', $costRow);                    // gain/loss: unknown, not ₹0.00
        $this->assertStringNotContainsString('₹0.00', $costRow);

        $this->assertStringContainsString('TSTC · USD', $marketRow);
        $this->assertStringNotContainsString('at cost', $marketRow);

        $this->assertStringNotContainsString('USD', $rupeeRow);
        $this->assertStringNotContainsString('at cost', $rupeeRow);
    }

    // ─── sources line ────────────────────────────────────────────────────

    public function test_the_report_says_how_many_of_a_clients_files_it_was_built_from_and_names_the_ones_left_out(): void
    {
        $complete = $this->render([$this->rupeeAsset()], ['included' => ['groww.xlsx', 'us-stocks.xls'], 'skipped' => []]);
        $partial = $this->render([$this->rupeeAsset()], [
            'included' => ['groww.xlsx'],
            'skipped' => ['Rajesh Kumar/us-stocks.xls' => PortfolioParser::NO_FX_RATE_MESSAGE, 'Rajesh Kumar/statement.pdf' => 'File type .pdf is not supported.'],
        ]);

        $this->assertStringContainsString('Built from 2 of 2 files.', $complete);
        $this->assertStringNotContainsString('Not included:', $complete);

        $this->assertStringContainsString('Built from 1 of 3 files.', $partial);
        $this->assertStringContainsString('Not included:', $partial);
        $this->assertStringContainsString('us-stocks.xls — US-dollar holdings can&#039;t be valued yet: no exchange rate is set.', $partial);
        $this->assertStringContainsString('statement.pdf — File type .pdf is not supported.', $partial);
    }

    // ─── every line is absent when it does not apply ─────────────────────

    public function test_an_all_rupee_single_file_report_has_none_of_these_lines(): void
    {
        $html = $this->render([$this->rupeeAsset()]);

        foreach (['US-dollar', 'per USD', 'days old', 'valued at cost', 'at cost', 'Built from', 'Not included', '· USD'] as $absent) {
            $this->assertStringNotContainsString($absent, $html);
        }

        // The box now appears on every report that has a score not taken from
        // market data, and for this one it holds that line and nothing else.
        $this->assertSame("Risk scores: 1 estimated from the fund's name.", $this->box($html));
    }

    public function test_a_report_whose_scores_are_all_from_market_data_has_no_box_and_no_heading_at_all(): void
    {
        // Rupees, a single file, and every holding a stock scored from a classification.
        $liveStock = fn (string $name) => new PortfolioAsset([
            'name' => $name, 'asset_type' => 'stock', 'quantity' => 10,
            'current_value' => 25000, 'invested_value' => 20000, 'profit_loss' => 5000,
            'risk_score' => 50, 'risk_level' => 'MEDIUM',
            'meta' => ['invested_value_source' => 'file', 'stock_risk' => ['source' => \App\Services\RiskEngine\AssetRiskScorer::SOURCE_LIVE, 'confidence' => 0.9]],
        ]);

        $html = $this->render([$liveStock('Example Industries'), $liveStock('Example Steel')]);

        $this->assertNull($this->box($html));
        $this->assertStringNotContainsString(self::HEADING, $html);
        $this->assertStringNotContainsString('Risk scores:', $html);
    }

    // ─── the job hands the report what it needs ──────────────────────────

    public function test_the_generated_report_of_a_client_folder_carries_the_currency_valuation_and_sources_lines(): void
    {
        Storage::fake('portfolios');
        Mail::fake();
        $this->mock(StockRiskService::class)->shouldReceive('classifyBatch')->andReturn([]);

        // Capture the HTML the job sends to the PDF renderer.
        $rendered = [];
        Pdf::shouldReceive('loadView')->andReturnUsing(function (string $view, array $data) use (&$rendered) {
            $rendered[$data['portfolio']->name] = view($view, $data)->render();

            return \Mockery::mock(\Barryvdh\DomPDF\PDF::class)->shouldReceive('output')->andReturn('%PDF-test')->getMock();
        });

        FxRate::create(['currency' => 'USD', 'rate' => 95.985, 'as_of' => '2026-09-29', 'source' => self::SOURCE, 'entered_by' => 'test', 'entered_at' => now()]);

        $user = User::factory()->create();
        $zipPath = Storage::disk('portfolios')->path('uploads/clients.zip');
        @mkdir(dirname($zipPath), 0777, true);
        Fixtures::zip($zipPath, [
            'Rajesh Kumar/groww.xlsx' => [Fixtures::class, 'growwMutualFundHoldings'],
            'Rajesh Kumar/us-stocks.xls' => [Fixtures::class, 'indmoneyUsStocks'],
            'Rajesh Kumar/statement.pdf' => "%PDF-1.4\n%useless\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>",
            'priya_sharma.csv' => "name,asset_type,current_value\nHDFC Bank,stock,20000\n",
        ]);

        ProcessPortfolioFile::dispatchSync(PortfolioFile::create([
            'user_id' => $user->id, 'original_name' => 'clients.zip', 'stored_name' => 'clients.zip', 'path' => 'uploads/clients.zip',
            'mime_type' => 'application/zip', 'file_size' => filesize($zipPath),
            'status' => PortfolioFile::STATUS_PENDING, 'meta' => ['extension' => 'zip'],
        ]));

        $folderClient = $rendered['Rajesh Kumar'];

        $this->assertStringContainsString('US-dollar holdings converted at ₹95.985 per USD (Test rate — invented, as of 29 Sep 2026).', $folderClient);
        $this->assertStringContainsString('16 holdings are valued at cost', $folderClient);
        $this->assertStringContainsString('Built from 2 of 3 files.', $folderClient);
        $this->assertStringContainsString('statement.pdf — File type .pdf is not supported.', $folderClient);
        $this->assertSame(16, substr_count($folderClient, '&middot; USD'));

        // A single file at the ZIP root: no sources line, nothing else either —
        // the box holds only the line about how its one score was arrived at.
        $this->assertSame('Risk scores: 1 by asset category.', $this->box($rendered['Priya Sharma']));
    }
}
