<?php

declare(strict_types=1);

namespace Tests\Unit\Amfi;

use App\Exceptions\AmfiSyncFailed;
use App\Services\Amfi\NavAllParser;
use App\Services\Amfi\SchemeNameNormalizer;
use PHPUnit\Framework\TestCase;

class NavAllParserTest extends TestCase
{
    private function parse(string $contents): array
    {
        return (new NavAllParser(new SchemeNameNormalizer))->parse($contents);
    }

    private function fixture(): array
    {
        return $this->parse(file_get_contents(__DIR__.'/../../Fixtures/amfi/NAVAll.txt'));
    }

    private function row(array $parsed, string $code): array
    {
        $row = collect($parsed['rows'])->firstWhere('scheme_code', $code);
        $this->assertNotNull($row, "scheme {$code} not parsed");

        return $row;
    }

    public function test_it_parses_the_fixture(): void
    {
        $parsed = $this->fixture();

        $this->assertCount(48, $parsed['rows']);

        $row = $this->row($parsed, '135762');
        $this->assertSame('open', $row['scheme_type']);
        $this->assertSame('Children’s Fund', $row['broad_category']);
        $this->assertSame("Childrens' Fund", $row['sub_category']);
        $this->assertNull($row['isin_reinvest']);
        $this->assertSame('INF846K01WO1', $row['isin_growth']);
        $this->assertSame(28.6173, $row['latest_nav']);
        $this->assertSame('2026-10-23', $row['nav_date']);
        $this->assertSame('Axis Mutual Fund', $row['amc']);
    }

    public function test_category_headers_split_on_the_first_spaced_hyphen(): void
    {
        $parsed = $this->fixture();

        $etf = $this->row($parsed, '153940');
        $this->assertSame('Exchange Traded Funds (ETFs)', $etf['broad_category']);
        $this->assertSame('Other ETF', $etf['sub_category']);

        $retirement = collect($parsed['rows'])->firstWhere('sub_category', 'Retirement Fund');
        $this->assertSame('Solution Oriented Schemes', $retirement['broad_category']);

        $closed = collect($parsed['rows'])->firstWhere('scheme_code', '147236');
        $this->assertSame('close', $closed['scheme_type']);
        $this->assertSame('Income', $closed['broad_category']);
        $this->assertNull($closed['sub_category']);
    }

    public function test_file_date_is_the_most_common_date_not_the_latest(): void
    {
        $this->assertSame('2026-10-23', $this->fixture()['file_date']->toDateString());
    }

    public function test_legacy_header_without_plan_and_option_parses(): void
    {
        $parsed = $this->parse(
            "Scheme Code;ISIN Div Payout/ ISIN Growth;ISIN Div Reinvestment;Scheme Name;Net Asset Value;Date\r\n"
            ."\r\nOpen Ended Schemes(Equity Scheme - Large Cap Fund)\r\n\r\nAxis Mutual Fund\r\n\r\n"
            ."100;INF000000001;-;Axis Bluechip Fund;12.5;23-Oct-2026\r\n"
        );

        $this->assertCount(1, $parsed['rows']);
        $this->assertSame('INF000000001', $parsed['rows'][0]['isin_growth']);
        $this->assertSame(12.5, $parsed['rows'][0]['latest_nav']);
    }

    public function test_unrecognised_header_throws(): void
    {
        $this->expectException(AmfiSyncFailed::class);

        $this->parse("<html><body>Service unavailable</body></html>\nsomething;else\n");
    }

    public function test_empty_input_throws(): void
    {
        $this->expectException(AmfiSyncFailed::class);

        $this->parse("  \n");
    }
}
