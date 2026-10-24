<?php

namespace Tests\Feature;

use App\Jobs\ProcessPortfolioFile;
use App\Models\MfScheme;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\PortfolioFile;
use App\Models\User;
use App\Services\Amfi\SchemeNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An uploaded portfolio's fund holdings carry the AMFI scheme they matched,
 * how they matched and how surely; stocks carry none of it.
 */
class HoldingSchemeMatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['amfi.min_rows' => 5]);
        $this->travelTo(Carbon::parse('2026-10-24 06:30', 'Asia/Kolkata'));
        $this->artisan('amfi:sync', ['--source' => base_path('tests/Fixtures/amfi/NAVAll.txt')])->assertSuccessful();
    }

    private function process(string $csv): void
    {
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
    }

    public function test_each_fund_holding_stores_its_match_and_a_stock_stores_none(): void
    {
        $this->process(implode("\n", [
            'name,asset_type,isin,current_value',
            'Gold ETF units,etf,INF204KB17I5,10000',
            'HDFC Corporate Bond Fund - Direct Plan - Growth,mutual_fund,,20000',
            'Parag Parikh Flexi Cap Fnud,mutual_fund,,30000',
            'Acme Moonshot Fund,mutual_fund,,5000',
            'Reliance Industries,stock,INE002A01018,25000',
        ]));

        $asset = fn (string $name) => PortfolioAsset::where('name', $name)->firstOrFail();

        $this->assertSame(['140088', 'isin', '1.000'], [$asset('Gold ETF units')->scheme_code, $asset('Gold ETF units')->match_method, $asset('Gold ETF units')->match_confidence]);
        // A name matches the scheme, not one plan of it: either HDFC Corporate Bond row will do.
        $this->assertContains($asset('HDFC Corporate Bond Fund - Direct Plan - Growth')->scheme_code, ['113070', '118987']);
        $this->assertSame('exact', $asset('HDFC Corporate Bond Fund - Direct Plan - Growth')->match_method);
        $this->assertSame(['122639', 'fuzzy'], [$asset('Parag Parikh Flexi Cap Fnud')->scheme_code, $asset('Parag Parikh Flexi Cap Fnud')->match_method]);
        $this->assertSame([null, 'none'], [$asset('Acme Moonshot Fund')->scheme_code, $asset('Acme Moonshot Fund')->match_method]);
        $this->assertSame([null, null, null], [$asset('Reliance Industries')->scheme_code, $asset('Reliance Industries')->match_method, $asset('Reliance Industries')->match_confidence]);
    }

    public function test_a_stock_named_exactly_like_a_fund_is_never_matched_to_it(): void
    {
        // A real AMFI row (24 Oct 2026). Motilal Oswal Financial Services Ltd
        // is also a listed company, so the bare name is both a stock and a fund.
        MfScheme::create([
            'scheme_code' => '154142', 'isin_growth' => 'INF247L01GN5', 'scheme_name' => 'Motilal Oswal Financial Services Fund',
            'normalized_name' => app(SchemeNameNormalizer::class)->normalize('Motilal Oswal Financial Services Fund'),
            'amc' => 'Motilal Oswal Mutual Fund', 'scheme_type' => 'open', 'broad_category' => 'Equity Scheme',
            'sub_category' => 'Sectoral/ Thematic', 'latest_nav' => 10.0233, 'nav_date' => '2026-10-23',
        ]);

        $this->process(implode("\n", [
            'name,asset_type,isin,current_value',
            'Motilal Oswal Financial Services,stock,INE338I01027,10000',
        ]));
        $this->process(implode("\n", [
            'name,asset_type,isin,current_value',
            'Motilal Oswal Financial Services,mutual_fund,,10000',
        ]));

        [$stock, $fund] = PortfolioAsset::where('name', 'Motilal Oswal Financial Services')->orderBy('id')->get()->all();

        $this->assertSame(['stock', null, null], [$stock->asset_type, $stock->scheme_code, $stock->match_method]);
        $this->assertSame(['mutual_fund', '154142', 'exact'], [$fund->asset_type, $fund->scheme_code, $fund->match_method]);
    }
}
