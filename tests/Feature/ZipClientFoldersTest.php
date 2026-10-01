<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use App\Services\RiskEngine\PortfolioParser;
use App\Services\StockRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BrokerExportFixtures as Fixtures;
use Tests\TestCase;

/**
 * A ZIP organised as one folder per client: the folder name is the client,
 * and every broker file in the folder is merged into that client's single
 * portfolio. Files at the ZIP root keep the older one-file-one-client rule.
 *
 * All fixtures are generated from invented data (tests/Support/BrokerExportFixtures.php).
 */
class ZipClientFoldersTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = "Scheme Name,ISIN,Units,Invested Value,Current Value\n";

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('portfolios');
        Mail::fake();

        $this->mock(StockRiskService::class)->shouldReceive('classifyBatch')->andReturn([]);

        $this->user = User::factory()->create();
    }

    /** Upload-and-process a ZIP built from $entries; returns the parent file. */
    private function processZip(array $entries, string $zipName = 'clients.zip'): PortfolioFile
    {
        $zipPath = Storage::disk('portfolios')->path('uploads/'.$zipName);
        @mkdir(dirname($zipPath), 0777, true);
        Fixtures::zip($zipPath, $entries);

        $parent = PortfolioFile::create([
            'user_id' => $this->user->id,
            'original_name' => $zipName,
            'stored_name' => $zipName,
            'path' => 'uploads/'.$zipName,
            'mime_type' => 'application/zip',
            'file_size' => filesize($zipPath),
            'status' => PortfolioFile::STATUS_PENDING,
            'meta' => ['extension' => 'zip'],
        ]);

        ProcessPortfolioFile::dispatchSync($parent);

        return $parent->fresh();
    }

    private function csv(array $rows): string
    {
        return self::CSV_HEADER.implode("\n", array_map(fn ($row) => implode(',', $row), $rows))."\n";
    }

    private function summary(PortfolioFile $parent): string
    {
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('portfolios')->path($parent->bundle_report_path));
        $summary = $zip->getFromName('_SUMMARY.txt');
        $zip->close();

        return $summary;
    }

    private function bundleEntries(PortfolioFile $parent): array
    {
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('portfolios')->path($parent->bundle_report_path));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        sort($names);

        return $names;
    }

    private function clientNames(): array
    {
        return Portfolio::where('user_id', $this->user->id)->orderBy('id')->pluck('name')->all();
    }

    // ─── 1. folder name is the client name ───────────────────────────────

    public function test_each_client_folder_becomes_one_client_named_from_the_folder(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/daac358a-1592-4bf0-a3b2-7e9dd2084885.xlsx' => [Fixtures::class, 'growwMutualFundHoldings'],
            "Priya_D'Souza/IND-HOLDINGS_REPORTXX00000000-V04.csv" => $this->csv([['Example Gilt Fund', 'INF000TEST09', 10, 1000, 1100]]),
        ]);

        // Folder names, capitalisation preserved, underscore to space; the
        // brokers' UUID / report-number filenames play no part.
        $this->assertSame(['Rajesh Kumar', "Priya D'Souza"], $this->clientNames());
        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $parent->status);
        $this->assertSame(2, $parent->meta['client_count']);
        $this->assertSame(2, $parent->meta['bundle_processed_count']);
    }

    public function test_the_outer_zip_name_plays_no_part_in_client_identity(): void
    {
        $this->processZip(['Rajesh Kumar/holdings.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]])], 'Some Other Person.zip');

        $this->assertSame(['Rajesh Kumar'], $this->clientNames());
    }

    // ─── 2–3. one folder, several brokers, one merged portfolio ──────────

    public function test_three_broker_files_in_one_folder_become_one_merged_portfolio(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/broker-a.xlsx' => fn (string $path) => Fixtures::holdingsWorkbook($path, [
                ['Example Flexi Cap Fund', 'INF000TEST01', 100, 10000, 12000],
                ['Example Liquid Fund', 'INF000TEST02', 50, 5000, 5500],
            ]),
            'Rajesh Kumar/broker-b.csv' => $this->csv([
                ['EXAMPLE FLEXI CAP FUND - DIRECT', 'INF000TEST01', 40, 4400, 4800],   // same ISIN, other broker
                ['Example Industries', '', 10, 2000, 2500],
            ]),
            'Rajesh Kumar/broker-c.xlsx' => fn (string $path) => Fixtures::holdingsWorkbook($path, [
                ['Example Gilt Fund', 'INF000TEST04', 10, 1000, 900],
            ]),
        ]);

        $this->assertSame(['Rajesh Kumar'], $this->clientNames());

        $portfolio = Portfolio::where('name', 'Rajesh Kumar')->sole();
        $assets = PortfolioAsset::where('portfolio_id', $portfolio->id)->orderBy('id')->get();

        // 5 rows across 3 files; the flexi-cap fund is held through two
        // brokers and is summed into one holding.
        $this->assertCount(4, $assets);

        $flexi = $assets->firstWhere('isin', 'INF000TEST01');
        $this->assertSame('140.0000', $flexi->quantity);
        $this->assertSame('14400.00', $flexi->invested_value);
        $this->assertSame('16800.00', $flexi->current_value);
        $this->assertSame('2400.00', $flexi->profit_loss);
        $this->assertSame(['broker-a.xlsx', 'broker-b.csv'], array_column($flexi->meta['sources'], 'source_file'));

        // One score and one report for the client; totals are the sum of all three files.
        $this->assertSame(1, RiskScore::where('portfolio_id', $portfolio->id)->count());
        $this->assertSame('25700.00', $portfolio->fresh()->total_value);   // 12000 + 5500 + 4800 + 2500 + 900
        $this->assertSame(1, PortfolioFile::where('portfolio_id', $portfolio->id)->whereNotNull('report_path')->count());
        $this->assertSame(3, PortfolioFile::where('portfolio_id', $portfolio->id)->where('status', PortfolioFile::STATUS_PROCESSED)->count());

        $this->assertStringContainsString('OK  Rajesh Kumar — built from: broker-a.xlsx, broker-b.csv, broker-c.xlsx', $this->summary($parent));
    }

    // ─── 4. mixed cost knowledge ─────────────────────────────────────────

    public function test_a_holding_with_cost_in_one_file_and_none_in_another_has_unknown_merged_cost(): void
    {
        $this->processZip([
            'Rajesh Kumar/with-cost.xlsx' => fn (string $path) => Fixtures::holdingsWorkbook($path, [
                ['Example Flexi Cap Fund', 'INF000TEST01', 100, 10000, 12000],
            ]),
            'Rajesh Kumar/no-cost.xlsx' => fn (string $path) => Fixtures::holdingsWorkbook($path, [
                ['Example Flexi Cap Fund', 'INF000TEST01', 50, null, 6000],
            ], withCost: false),
        ]);

        $asset = PortfolioAsset::sole();

        $this->assertSame('18000.00', $asset->current_value);
        $this->assertNull($asset->invested_value);      // not the partial 10,000
        $this->assertNull($asset->profit_loss);
        $this->assertSame('unknown', $asset->meta['invested_value_source']);
        $this->assertSame([true, false], array_column($asset->meta['sources'], 'cost_known'));
    }

    // ─── 5. C1 — a US-dollar file in a folder ────────────────────────────

    public function test_a_us_dollar_file_is_skipped_and_the_client_is_scored_from_the_rupee_files(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/groww.xlsx' => [Fixtures::class, 'growwMutualFundHoldings'],
            'Rajesh Kumar/us-stocks.xls' => [Fixtures::class, 'indmoneyUsStocks'],
        ]);

        $usd = sprintf(PortfolioParser::USD_MESSAGE, 16);
        $lead = PortfolioFile::where('original_name', 'groww.xlsx')->sole();

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $lead->status);
        $this->assertSame(3, PortfolioAsset::count());                       // the Groww holdings only
        $this->assertSame(['Rajesh Kumar/us-stocks.xls' => $usd], $lead->meta['client_sources']['skipped']);
        $this->assertSame('Scored from 1 of 2 files; skipped: us-stocks.xls — '.$usd, $lead->meta['parse_warnings'][0]);

        $skippedFile = PortfolioFile::where('original_name', 'us-stocks.xls')->sole();
        $this->assertSame(PortfolioFile::STATUS_FAILED, $skippedFile->status);
        $this->assertSame($usd, $skippedFile->meta['error_message']);

        $summary = $this->summary($parent);
        $this->assertStringContainsString('NOT INCLUDED in this client\'s portfolio:', $summary);
        $this->assertStringContainsString('us-stocks.xls — '.$usd, $summary);
    }

    public function test_a_client_whose_files_are_all_us_dollar_fails_with_that_reason(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/us-stocks.xls' => [Fixtures::class, 'indmoneyUsStocks'],
            'Priya Sharma/holdings.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
        ]);

        $usd = sprintf(PortfolioParser::USD_MESSAGE, 16);
        $lead = PortfolioFile::where('original_name', 'us-stocks.xls')->sole();

        $this->assertSame(PortfolioFile::STATUS_FAILED, $lead->status);
        $this->assertSame($usd, $lead->meta['error_message']);
        $this->assertStringContainsString('Rajesh Kumar: '.$usd, $this->summary($parent));
        $this->assertSame(1, $parent->meta['bundle_processed_count']);        // the other client is unaffected
    }

    // ─── 6. C2 / §F — partial failure, including a PDF ───────────────────

    public function test_an_unusable_file_is_reported_and_the_client_is_scored_from_the_rest(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/good.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/no-header.csv' => "just,some\nvalues,here\n",
            'Rajesh Kumar/statement.pdf' => "%PDF-1.4\n%useless\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>",
        ]);

        $lead = PortfolioFile::where('original_name', 'good.csv')->sole();

        $this->assertSame(PortfolioFile::STATUS_PROCESSED, $lead->status);
        $this->assertSame(1, PortfolioAsset::count());
        $this->assertSame([
            'Rajesh Kumar/no-header.csv' => PortfolioParser::NO_HEADER_MESSAGE,
            'Rajesh Kumar/statement.pdf' => 'File type .pdf is not supported. Supported: CSV, XLSX, XLS, or a ZIP of these.',
        ], $lead->meta['client_sources']['skipped']);
        $this->assertStringStartsWith('Scored from 1 of 3 files; skipped: ', $lead->meta['parse_warnings'][0]);

        $summary = $this->summary($parent);
        $this->assertStringContainsString('OK  Rajesh Kumar — built from: good.csv', $summary);
        $this->assertStringContainsString('statement.pdf — File type .pdf is not supported', $summary);
        $this->assertSame(1, $parent->meta['bundle_processed_count']);
    }

    // ─── 7. C3 — duplicates ──────────────────────────────────────────────

    public function test_a_byte_identical_duplicate_in_a_folder_is_kept_once(): void
    {
        $file = $this->csv([['Example Gilt Fund', 'INF000TEST09', 10, 1000, 1100]]);

        $parent = $this->processZip([
            'Rajesh Kumar/holdings.csv' => $file,
            'Rajesh Kumar/holdings (1).csv' => $file,
        ]);

        $asset = PortfolioAsset::sole();

        $this->assertSame('10.0000', $asset->quantity);            // not doubled to 20
        $this->assertSame('1100.00', $asset->current_value);
        $this->assertDatabaseMissing('portfolio_files', ['original_name' => 'holdings (1).csv']);
        $this->assertSame(
            ['Rajesh Kumar' => ['Rajesh Kumar/holdings (1).csv' => 'Duplicate of holdings.csv (identical file)']],
            $parent->meta['client_skip_reasons'],
        );

        // Dropped at extraction, but still named in the upload-history warning.
        $this->assertSame(
            'Scored from 1 of 2 files; skipped: holdings (1).csv — Duplicate of holdings.csv (identical file)',
            PortfolioFile::where('original_name', 'holdings.csv')->sole()->meta['parse_warnings'][0],
        );
        $this->assertStringContainsString('holdings (1).csv — Duplicate of holdings.csv (identical file)', $this->summary($parent));
    }

    public function test_a_re_download_with_different_bytes_but_identical_holdings_is_kept_once(): void
    {
        $rows = [['Example Gilt Fund', 'INF000TEST09', 10, 1000, 1100], ['Example Liquid Fund', 'INF000TEST02', 5, 500, 520]];

        $this->processZip([
            'Rajesh Kumar/monday.csv' => "Holdings report as on 28-09-2026\n".$this->csv($rows),
            'Rajesh Kumar/tuesday.csv' => "Holdings report as on 29-09-2026\n".$this->csv(array_reverse($rows)),
        ]);

        $this->assertSame(2, PortfolioAsset::count());
        $this->assertSame('10.0000', PortfolioAsset::where('isin', 'INF000TEST09')->sole()->quantity);

        $lead = PortfolioFile::where('original_name', 'monday.csv')->sole();
        $this->assertSame(['Rajesh Kumar/tuesday.csv' => 'Duplicate of monday.csv (identical holdings)'], $lead->meta['client_sources']['skipped']);
    }

    // ─── skip reasons are never lost to a name collision ─────────────────

    public function test_same_named_files_in_two_client_folders_each_keep_their_own_outcome(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/holdings.xlsx' => [Fixtures::class, 'growwMutualFundHoldings'],
            'Rajesh Kumar/other.csv' => $this->csv([['Example Gilt Fund', 'INF000TEST09', 10, 1000, 1100]]),
            'Priya Sharma/holdings.xlsx' => [Fixtures::class, 'indmoneyUsStocks'],   // same filename, US-dollar
            'Priya Sharma/other.csv' => $this->csv([['Example Liquid Fund', 'INF000TEST02', 5, 500, 520]]),
        ]);

        $usd = sprintf(PortfolioParser::USD_MESSAGE, 16);
        $leadOf = fn (string $client) => PortfolioFile::where('portfolio_id', Portfolio::where('name', $client)->sole()->id)
            ->whereNotNull('report_path')->sole();

        // Rajesh: both files used, nothing skipped, no warning.
        $this->assertSame([], $leadOf('Rajesh Kumar')->meta['client_sources']['skipped']);
        $this->assertSame([], $leadOf('Rajesh Kumar')->meta['parse_warnings']);

        // Priya: her holdings.xlsx — and only hers — is skipped, with the reason.
        $this->assertSame(['Priya Sharma/holdings.xlsx' => $usd], $leadOf('Priya Sharma')->meta['client_sources']['skipped']);
        $this->assertSame('Scored from 1 of 2 files; skipped: holdings.xlsx — '.$usd, $leadOf('Priya Sharma')->meta['parse_warnings'][0]);

        $summary = $this->summary($parent);
        $rajesh = substr($summary, strpos($summary, 'OK  Rajesh Kumar'), strpos($summary, 'OK  Priya Sharma') - strpos($summary, 'OK  Rajesh Kumar'));
        $priya = substr($summary, strpos($summary, 'OK  Priya Sharma'));

        $this->assertStringContainsString('built from: holdings.xlsx, other.csv', $rajesh);
        $this->assertStringNotContainsString('NOT INCLUDED', $rajesh);
        $this->assertStringContainsString('built from: other.csv', $priya);
        $this->assertStringContainsString('holdings.xlsx — '.$usd, $priya);
    }

    public function test_same_named_files_dropped_at_extraction_in_one_client_each_keep_their_reason(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/groww/holdings.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/zerodha/holdings.csv' => '',                                    // empty
            'Rajesh Kumar/upstox/holdings.csv' => "<?php system(\$_GET['cmd']); ?>",      // not a CSV
            'Priya Sharma/other.csv' => $this->csv([['Example Liquid Fund', 'INF000TEST02', 5, 500, 520]]),   // a second client: 'Rajesh Kumar' is not a lone outer folder
        ]);

        $lead = PortfolioFile::where('original_name', 'holdings.csv')->sole();
        $skipped = $lead->meta['client_sources']['skipped'];

        // Two files with the same bare name, two different reasons, both kept.
        // (The detected type in the second reason is finfo's wording and
        // varies by system, so only its stable part is asserted.)
        $this->assertSame(['Rajesh Kumar/zerodha/holdings.csv', 'Rajesh Kumar/upstox/holdings.csv'], array_keys($skipped));
        $this->assertSame('File is empty (0 bytes)', $skipped['Rajesh Kumar/zerodha/holdings.csv']);
        $this->assertStringStartsWith('File content does not match a supported type (claimed .csv', $skipped['Rajesh Kumar/upstox/holdings.csv']);
        $this->assertSame(['Rajesh Kumar' => $skipped], $parent->meta['client_skip_reasons']);

        $warning = $lead->meta['parse_warnings'][0];
        $this->assertStringStartsWith('Scored from 1 of 3 files; skipped: zerodha/holdings.csv — File is empty (0 bytes); upstox/holdings.csv — File content does not match a supported type', $warning);

        $summary = $this->summary($parent);
        $this->assertStringContainsString('built from: groww/holdings.csv', $summary);
        $this->assertStringContainsString('zerodha/holdings.csv — File is empty (0 bytes)', $summary);
        $this->assertStringContainsString('upstox/holdings.csv — File content does not match a supported type', $summary);
    }

    public function test_same_named_files_skipped_at_parsing_in_one_client_each_keep_their_reason(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/a/statement.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/b/statement.csv' => "just,some\nvalues,here\n",
            'Rajesh Kumar/c/statement.csv' => "Stock Symbol,Quantity,Avg. Price ($),Total Value ($)\nTSTA,1,10,10\n",
            'Priya Sharma/other.csv' => $this->csv([['Example Liquid Fund', 'INF000TEST02', 5, 500, 520]]),   // a second client: 'Rajesh Kumar' is not a lone outer folder
        ]);

        $expected = [
            'Rajesh Kumar/b/statement.csv' => PortfolioParser::NO_HEADER_MESSAGE,
            'Rajesh Kumar/c/statement.csv' => sprintf(PortfolioParser::USD_MESSAGE, 1),
        ];
        $lead = PortfolioFile::where('portfolio_id', Portfolio::where('name', 'Rajesh Kumar')->sole()->id)->whereNotNull('report_path')->sole();

        $this->assertSame($expected, $lead->meta['client_sources']['skipped']);
        $this->assertStringContainsString('b/statement.csv — '.PortfolioParser::NO_HEADER_MESSAGE, $lead->meta['parse_warnings'][0]);
        $this->assertStringContainsString('c/statement.csv — '.sprintf(PortfolioParser::USD_MESSAGE, 1), $lead->meta['parse_warnings'][0]);

        $summary = $this->summary($parent);
        $this->assertStringContainsString('built from: a/statement.csv', $summary);
        $this->assertStringContainsString('b/statement.csv — '.PortfolioParser::NO_HEADER_MESSAGE, $summary);
        $this->assertStringContainsString('c/statement.csv — '.sprintf(PortfolioParser::USD_MESSAGE, 1), $summary);
    }

    // ─── 8. flat ZIP — unchanged ─────────────────────────────────────────

    public function test_a_flat_zip_still_makes_one_client_per_file_named_from_the_filename(): void
    {
        $parent = $this->processZip([
            'rajesh_kumar.csv' => "name,asset_type,current_value\nHDFC Bank,stock,20000\n",
            'priya-sharma.csv' => "name,asset_type,current_value\nInfosys,stock,15000\n",
        ]);

        $this->assertSame(['Rajesh Kumar', 'Priya Sharma'], $this->clientNames());   // title-cased from filenames, as before

        $children = PortfolioFile::where('meta->extracted_from_zip_id', $parent->id)->orderBy('id')->get();
        $this->assertCount(2, $children);
        foreach ($children as $child) {
            $this->assertArrayNotHasKey('client_folder', $child->meta);
            $this->assertArrayNotHasKey('merged_into_file_id', $child->meta);
            $this->assertNotNull($child->report_path);
            $this->assertArrayNotHasKey('sources', PortfolioAsset::where('portfolio_id', $child->portfolio_id)->sole()->meta);
        }

        $summary = $this->summary($parent);
        $this->assertStringContainsString('OK  Rajesh Kumar (rajesh_kumar.csv)', $summary);
        $this->assertStringNotContainsString('NOTES', $summary);
        $this->assertSame(['_SUMMARY.txt', 'priya_sharma_report.pdf', 'rajesh_kumar_report.pdf'], $this->bundleEntries($parent));
    }

    // ─── 9. mixed root files and folders ─────────────────────────────────

    public function test_root_files_and_client_folders_in_one_zip_are_each_handled_by_their_own_rule(): void
    {
        $parent = $this->processZip([
            'amit_verma.csv' => "name,asset_type,current_value\nHDFC Bank,stock,20000\n",
            'Rajesh Kumar/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/b.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['Amit Verma', 'Rajesh Kumar'], $this->clientNames());
        $this->assertSame(2, PortfolioAsset::where('portfolio_id', Portfolio::where('name', 'Rajesh Kumar')->sole()->id)->count());
        $this->assertStringContainsString('This ZIP has both client folders and files at the top level.', $this->summary($parent));
    }

    public function test_subfolders_inside_a_client_folder_belong_to_that_client(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/groww/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/zerodha/b.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
            'Priya Sharma/other.csv' => $this->csv([['Example Liquid Fund', 'INF000TEST02', 5, 500, 520]]),   // a second client: 'Rajesh Kumar' is not a lone outer folder
        ]);

        $this->assertSame(['Rajesh Kumar', 'Priya Sharma'], $this->clientNames());
        $this->assertSame(2, PortfolioAsset::where('portfolio_id', Portfolio::where('name', 'Rajesh Kumar')->sole()->id)->count());
        $this->assertStringContainsString("Subfolders inside 'Rajesh Kumar' were ignored for naming", $this->summary($parent));
        $this->assertStringNotContainsString('was treated as a container', $this->summary($parent));
    }

    // ─── an outer folder around the client folders ───────────────────────

    public function test_a_zip_made_by_compressing_a_parent_folder_is_unwrapped_into_its_client_folders(): void
    {
        $parent = $this->processZip([
            'clients/' => '',
            'clients/Asha Rao/a.csv' => $this->csv([['Example Gilt Fund', 'INF000TEST09', 10, 1000, 1100]]),
            'clients/Asha Rao/b.csv' => $this->csv([['Example Liquid Fund', 'INF000TEST02', 5, 500, 520]]),
            'clients/Vikram Rao/a.csv' => $this->csv([['Example Flexi Cap Fund', 'INF000TEST01', 1, 100, 110]]),
            '__MACOSX/clients/Asha Rao/._a.csv' => 'resource fork',      // what macOS adds beside it
        ]);

        // Two clients, not one client called "clients" holding everyone's money.
        $this->assertSame(['Asha Rao', 'Vikram Rao'], $this->clientNames());
        $this->assertSame(2, $parent->meta['client_count']);
        $this->assertSame(2, PortfolioAsset::where('portfolio_id', Portfolio::where('name', 'Asha Rao')->sole()->id)->count());
        $this->assertSame(1, PortfolioAsset::where('portfolio_id', Portfolio::where('name', 'Vikram Rao')->sole()->id)->count());

        $summary = $this->summary($parent);
        $this->assertSame(["Outer folder 'clients' was treated as a container."], $parent->meta['zip_notes']);
        $this->assertStringContainsString("Outer folder 'clients' was treated as a container.", $summary);
        $this->assertStringContainsString('OK  Asha Rao — built from: a.csv, b.csv', $summary);
        $this->assertStringContainsString('OK  Vikram Rao — built from: a.csv', $summary);
    }

    public function test_a_single_folder_holding_only_files_is_one_client_not_a_container(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/b.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['Rajesh Kumar'], $this->clientNames());
        $this->assertSame(2, PortfolioAsset::count());
        $this->assertSame([], $parent->meta['zip_notes']);
    }

    public function test_loose_files_inside_the_outer_folder_are_clients_of_their_own_and_noted(): void
    {
        $parent = $this->processZip([
            'clients/amit_verma.csv' => "name,asset_type,current_value\nHDFC Bank,stock,20000\n",
            'clients/Asha Rao/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'clients/Vikram Rao/a.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['Amit Verma', 'Asha Rao', 'Vikram Rao'], $this->clientNames());
        $this->assertSame([
            "Outer folder 'clients' was treated as a container.",
            'This ZIP has both client folders and files at the top level. Each top-level file was treated as one client, named from its filename.',
        ], $parent->meta['zip_notes']);
        $this->assertStringContainsString('OK  Amit Verma (amit_verma.csv)', $this->summary($parent));
    }

    public function test_a_lone_client_folder_with_broker_subfolders_is_unwrapped_and_the_summary_says_so(): void
    {
        // Indistinguishable from a wrapped ZIP. The subfolders become clients
        // with visibly wrong names — a mistake the advisor can see, unlike
        // several people merged into one score.
        $parent = $this->processZip([
            'Rajesh Kumar/groww/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/zerodha/b.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['groww', 'zerodha'], $this->clientNames());
        $this->assertStringContainsString("Outer folder 'Rajesh Kumar' was treated as a container.", $this->summary($parent));
    }

    public function test_only_one_outer_level_is_unwrapped(): void
    {
        $parent = $this->processZip([
            'outer/inner/Asha Rao/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'outer/inner/Vikram Rao/a.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['inner'], $this->clientNames());
        $this->assertSame([
            "Outer folder 'outer' was treated as a container.",
            "Subfolders inside 'inner' were ignored for naming: every file under a client folder belongs to that client.",
        ], $parent->meta['zip_notes']);
    }

    public function test_a_folder_is_not_a_container_when_a_file_sits_beside_it_at_the_zip_root(): void
    {
        $parent = $this->processZip([
            'amit_verma.csv' => "name,asset_type,current_value\nHDFC Bank,stock,20000\n",
            'Rajesh Kumar/groww/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Rajesh Kumar/zerodha/b.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['Amit Verma', 'Rajesh Kumar'], $this->clientNames());
        $this->assertStringNotContainsString('was treated as a container', $this->summary($parent));
    }

    public function test_skip_reasons_inside_an_unwrapped_zip_are_shown_relative_to_the_client_folder(): void
    {
        $parent = $this->processZip([
            'clients/Asha Rao/good.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'clients/Asha Rao/empty.csv' => '',
            'clients/Vikram Rao/a.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['Asha Rao' => ['Asha Rao/empty.csv' => 'File is empty (0 bytes)']], $parent->meta['client_skip_reasons']);
        $this->assertSame(
            'Scored from 1 of 2 files; skipped: empty.csv — File is empty (0 bytes)',
            PortfolioFile::where('original_name', 'good.csv')->sole()->meta['parse_warnings'][0],
        );
    }

    public function test_folders_differing_only_by_case_stay_separate_and_the_summary_says_so(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/a.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'rajesh kumar/b.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
            'Priya_Sharma/c.csv' => $this->csv([['Example Gilt Fund', '', 1, 100, 110]]),
            'Priya  Sharma/d.csv' => $this->csv([['Example Liquid Fund', '', 1, 100, 105]]),
        ]);

        // Same name after normalising → one client; case-only difference → two.
        $this->assertSame(['Rajesh Kumar', 'rajesh kumar', 'Priya Sharma'], $this->clientNames());
        $this->assertStringContainsString(
            "Folders 'Rajesh Kumar' and 'rajesh kumar' differ only by case — treated as two clients.",
            $this->summary($parent),
        );
    }

    // ─── 10. clutter ─────────────────────────────────────────────────────

    public function test_macosx_dotfiles_thumbs_and_empty_folders_are_ignored_silently(): void
    {
        $good = $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]);

        $parent = $this->processZip([
            'Rajesh Kumar/holdings.csv' => $good,
            '__MACOSX/Rajesh Kumar/._holdings.csv' => 'resource fork',
            '__MACOSX/extra.csv' => $good,
            'Rajesh Kumar/.DS_Store' => 'finder data',
            'Rajesh Kumar/Thumbs.db' => 'thumbnail cache',
            '.hidden/secret.csv' => $good,
            'Empty Client/' => '',
        ]);

        $this->assertSame(['Rajesh Kumar'], $this->clientNames());
        $this->assertSame(1, PortfolioFile::where('meta->extracted_from_zip_id', $parent->id)->count());
        $this->assertSame([], $parent->meta['skip_reasons']);
        $this->assertSame([], $parent->meta['client_skip_reasons']);
        $this->assertSame([], $parent->meta['failed_clients']);
    }

    public function test_a_folder_whose_files_all_fail_is_a_failed_client_not_a_silent_skip(): void
    {
        $parent = $this->processZip([
            'Rajesh Kumar/holdings.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'Broken Client/notes.txt' => 'not a portfolio',
            'Broken Client/inner.zip' => 'PK',
        ]);

        $this->assertSame(['Rajesh Kumar'], $this->clientNames());
        $this->assertSame(['Broken Client' => 'No usable files in this client folder.'], $parent->meta['failed_clients']);
        $this->assertSame(1, $parent->meta['bundle_failed_count']);

        $summary = $this->summary($parent);
        $this->assertStringContainsString('Broken Client: No usable files in this client folder.', $summary);
        $this->assertStringContainsString('skipped: inner.zip — Unsupported file type: .zip', $summary);
    }

    // ─── 11. folder names never reach the filesystem ─────────────────────

    public function test_a_traversal_folder_name_is_reported_and_creates_nothing(): void
    {
        $escapeTarget = dirname(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'etc'.DIRECTORY_SEPARATOR.'holdings.csv';

        $parent = $this->processZip([
            'Rajesh Kumar/holdings.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            '../../etc/holdings.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['Rajesh Kumar'], $this->clientNames());            // no client from the unsafe entry
        $this->assertSame(1, PortfolioAsset::count());
        $this->assertFileDoesNotExist($escapeTarget);
        $this->assertSame(['../../etc/holdings.csv' => 'Unsafe entry name — not extracted'], $parent->meta['skip_reasons']);
        $this->assertStringContainsString('../../etc/holdings.csv: Unsafe entry name — not extracted', $this->summary($parent));
    }

    public function test_extraction_names_temp_files_by_position_never_by_the_zip_entry_name(): void
    {
        $zipPath = Storage::disk('portfolios')->path('probe.zip');
        Fixtures::zip($zipPath, ['Some Folder/weird name; rm -rf.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]])]);

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zip_probe_'.uniqid();
        mkdir($tempDir, 0700);

        $zip = new \ZipArchive;
        $zip->open($zipPath);

        $job = new ProcessPortfolioFile(new PortfolioFile);
        $method = new \ReflectionMethod($job, 'extractZipEntry');
        $method->setAccessible(true);
        $entry = $method->invoke($job, $zip, 0, 'weird name; rm -rf.csv', $tempDir);
        $zip->close();

        $this->assertSame($tempDir.DIRECTORY_SEPARATOR.'entry_0.csv', $entry['temp_path']);
        $this->assertSame(['entry_0.csv'], array_values(array_diff(scandir($tempDir), ['.', '..'])));

        unlink($entry['temp_path']);
        rmdir($tempDir);
    }

    // ─── 12. Unicode folder names ────────────────────────────────────────

    public function test_unicode_folder_names_survive_to_the_portfolio_and_the_summary(): void
    {
        $parent = $this->processZip([
            'राजेश कुमार/holdings.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'José Fernández/holdings.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['राजेश कुमार', 'José Fernández'], $this->clientNames());

        $summary = $this->summary($parent);
        $this->assertStringContainsString('OK  राजेश कुमार — built from: holdings.csv', $summary);
        $this->assertStringContainsString('OK  José Fernández — built from: holdings.csv', $summary);

        // Two reports in the bundle: neither overwrote the other.
        $this->assertCount(3, $this->bundleEntries($parent));
    }

    public function test_two_clients_whose_names_reduce_to_the_same_file_name_both_keep_their_report(): void
    {
        $parent = $this->processZip([
            'A. Kumar/holdings.csv' => $this->csv([['Example Gilt Fund', '', 10, 1000, 1100]]),
            'A Kumar/holdings.csv' => $this->csv([['Example Liquid Fund', '', 5, 500, 520]]),
        ]);

        $this->assertSame(['_SUMMARY.txt', 'a_kumar_2_report.pdf', 'a_kumar_report.pdf'], $this->bundleEntries($parent));
    }

    // ─── limits ──────────────────────────────────────────────────────────

    public function test_a_client_folder_over_20_files_fails_and_five_or_more_is_logged_without_names(): void
    {
        Log::spy();

        $entries = [];
        foreach (range(1, 21) as $i) {
            $entries["Too Many/file{$i}.csv"] = $this->csv([["Example Fund {$i}", '', $i, 100, 110]]);
        }
        foreach (range(1, 5) as $i) {
            $entries["Rajesh Kumar/file{$i}.csv"] = $this->csv([["Example Fund {$i}", '', $i, 100, 110]]);
        }

        $parent = $this->processZip($entries);

        $this->assertSame(['Rajesh Kumar'], $this->clientNames());
        $this->assertSame(['Too Many' => 'This client folder has 21 files; the maximum is 20 per client.'], $parent->meta['failed_clients']);
        $this->assertSame(5, PortfolioAsset::count());

        Log::shouldHaveReceived('info')
            ->with('ZIP extraction: client folder with 5 or more files.', ['user_id' => $this->user->id, 'file_count' => 5])
            ->once();
    }
}
