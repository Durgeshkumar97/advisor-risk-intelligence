<?php

declare(strict_types=1);

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Synthetic broker exports, generated at test time.
 *
 * Each builder reproduces the STRUCTURE of a real export (title rows above the
 * header, the export's exact column names, blank rows and disclaimer blocks)
 * with entirely invented data: no real names, PANs, account numbers, ISINs or
 * holdings. Nothing here is a copy of a real file, and no binary fixture is
 * committed — the repository is public, and this source is the reviewable
 * provenance of every file the tests read.
 */
final class BrokerExportFixtures
{
    /**
     * Groww mutual-fund holdings (.xlsx): 9 title rows, header on row 10,
     * 3 holdings on rows 11–13.
     */
    public static function growwMutualFundHoldings(string $path): void
    {
        self::save($path, 'Xlsx', [
            1 => ['Holdings report as on 29-09-2026'],
            3 => ['Name', 'Test Investor'],
            5 => ['Summary'],
            6 => ['Total Invested value', 150000],
            7 => ['Total Current value', 162500],
            10 => ['Fund Name', 'ISIN', 'Total Units', 'Invested Value', 'Current Value', 'Folio Number'],
            11 => ['Example Bluechip Fund Direct Growth', 'INF000TEST01', 1234.567, 50000, 55000, '0000001/01'],
            12 => ['Example Midcap Opportunities Fund Direct Growth', 'INF000TEST02', 812.345, 60000, 64000, '0000002/01'],
            13 => ['Example Liquid Fund Direct Growth', 'INF000TEST03', 12.5, 40000, 43500, '0000003/01'],
        ]);
    }

    /**
     * INDmoney US-stocks holdings (.xls, real BIFF8): 4 account-detail rows,
     * blank rows 5–7, header on row 8 with "($)" markers, 16 holdings on
     * rows 9–24, blank rows 25–28, a disclaimer block on rows 29–35.
     */
    public static function indmoneyUsStocks(string $path): void
    {
        $rows = [
            1 => ['US Stocks Holdings Statement'],
            2 => ['Name: Test Investor'],
            3 => ['Account: TEST-ACCOUNT'],
            4 => ['Statement date: 29-09-2026'],
            8 => ['Stock Symbol', 'Holding Since', 'Quantity', 'Avg. Price ($)', 'Total Value ($)'],
        ];

        foreach (range(0, 15) as $i) {
            $quantity = round(0.25 + $i * 0.5, 4);
            $avgPrice = 10 + $i * 7.5;
            $rows[9 + $i] = ['TST'.chr(65 + $i), '01-01-2025', $quantity, $avgPrice, round($quantity * $avgPrice, 2)];
        }

        $rows[29] = ['Disclaimer: This statement is generated for information only.'];
        $rows[30] = ['Values are shown in US dollars and are not converted.'];
        $rows[31] = ['Holdings are held with the partner broker.'];
        $rows[32] = ['Please verify all figures with your broker.'];
        $rows[33] = ['This document is not a tax statement.'];
        $rows[34] = ['Past performance does not indicate future results.'];
        $rows[35] = ['End of statement.'];

        self::save($path, 'Xls', $rows);
    }

    /**
     * The D2-02 regression (.xlsx): row 3's ISIN cell is left empty, so the
     * saved sheet has no <c> element for it. A reader that ignores cell
     * coordinates shifts every later value one column left.
     */
    public static function sparseCells(string $path): void
    {
        self::save($path, 'Xlsx', [
            1 => ['Name', 'ISIN', 'Type', 'Invested Amount', 'Current Value', 'Quantity'],
            2 => ['Example Industries', 'INE000TEST01', 'Equity', 25000, 30000, 10],
            3 => ['Example Liquid Fund', null, 'Mutual Fund', 50000, 50500, 16.2],
        ]);
    }

    /**
     * An .xlsx whose Current Value in row 2 is a formula saved WITHOUT a cached
     * result, next to a plain value in row 3. There is no price column, so a
     * blank Current Value cannot be re-derived from quantity × price: reading
     * the cell as blank and evaluating the formula give different outcomes.
     */
    public static function formulaWithoutCachedValue(string $path): void
    {
        $spreadsheet = self::build([
            1 => ['Name', 'Quantity', 'Current Value'],
            2 => ['Formula Holding', 10, null],
            3 => ['Plain Holding', 4, 400],
        ]);
        $spreadsheet->getActiveSheet()->setCellValue('C2', '=B2*100');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
    }

    /**
     * A plain holdings workbook with the header on row 1.
     *
     * @param  list<list<mixed>>  $holdings  rows of [name, isin, units, invested value, current value];
     *                                       use null for a blank cell
     * @param  bool  $withCost  false omits the Invested Value column entirely
     */
    public static function holdingsWorkbook(string $path, array $holdings, bool $withCost = true, string $format = 'Xlsx'): void
    {
        $header = $withCost
            ? ['Scheme Name', 'ISIN', 'Units', 'Invested Value', 'Current Value']
            : ['Scheme Name', 'ISIN', 'Units', 'Current Value'];

        $rows = [1 => $header];

        foreach ($holdings as $i => $holding) {
            [$name, $isin, $units, $invested, $current] = $holding;
            $rows[$i + 2] = $withCost ? [$name, $isin, $units, $invested, $current] : [$name, $isin, $units, $current];
        }

        self::save($path, $format, $rows);
    }

    /**
     * Assemble a ZIP from generated files. Each entry is either literal file
     * content (string) or a callable that writes a file to the path it is
     * given — e.g. one of the builders in this class. An entry name ending in
     * "/" adds an empty folder.
     *
     * @param  array<string, string|callable(string): void>  $entries  keyed by entry name inside the ZIP
     */
    public static function zip(string $path, array $entries): void
    {
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $scratch = [];

        foreach ($entries as $entryName => $content) {
            if (str_ends_with($entryName, '/')) {
                $zip->addEmptyDir(rtrim($entryName, '/'));
            } elseif (is_string($content)) {
                $zip->addFromString($entryName, $content);
            } else {
                $scratch[] = $file = tempnam(sys_get_temp_dir(), 'fixture_');
                $content($file);
                $zip->addFromString($entryName, file_get_contents($file));
            }
        }

        $zip->close();
        array_map('unlink', $scratch);
    }

    /** @param array<int, list<mixed>> $rows keyed by 1-based row number */
    private static function save(string $path, string $format, array $rows): void
    {
        IOFactory::createWriter(self::build($rows), $format)->save($path);
    }

    /** @param array<int, list<mixed>> $rows keyed by 1-based row number */
    private static function build(array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowNumber => $cells) {
            foreach ($cells as $columnIndex => $value) {
                if ($value !== null) {
                    $sheet->setCellValue([$columnIndex + 1, $rowNumber], $value);
                }
            }
        }

        return $spreadsheet;
    }
}
