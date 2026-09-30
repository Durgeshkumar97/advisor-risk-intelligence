<?php

namespace App\Services\RiskEngine;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

/**
 * SpreadsheetRowReader
 *
 * Reads the first worksheet of an .xlsx (OOXML) or .xls (BIFF) file into
 * plain rows of strings, keyed by true column position — a blank cell stays a
 * blank slot instead of shifting the rest of the row left.
 *
 * The format is identified from the file's content, never its extension, and
 * only the Xlsx and Xls readers can ever be selected.
 *
 * Bounded: only the first $maxRows rows and MAX_COLUMNS columns are
 * materialised (a read filter), so an oversized sheet cannot exhaust a
 * shared-hosting worker. listWorksheetInfo() reports the sheet's full height
 * without loading it, so the caller can reject — not truncate — a sheet that
 * is taller than the cap.
 *
 * Formula cells are never evaluated. The value used is the result cached in
 * the file by the program that saved it (getOldCalculatedValue()); a formula
 * with no cached result is read as blank. An uploaded file must not be able to
 * trigger computation — PhpSpreadsheet's calculation engine includes functions
 * such as WEBSERVICE.
 */
class SpreadsheetRowReader
{
    public const MAX_COLUMNS = 50;

    /**
     * @return array{rows: list<list<string>>, total_rows: int, error: ?string}
     */
    public function read(string $path, int $maxRows): array
    {
        try {
            $type = IOFactory::identify($path, [IOFactory::READER_XLSX, IOFactory::READER_XLS]);

            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $sheetInfo = $reader->listWorksheetInfo($path)[0] ?? null;

            if ($sheetInfo === null) {
                return ['rows' => [], 'total_rows' => 0, 'error' => 'The spreadsheet has no worksheets.'];
            }

            $reader->setLoadSheetsOnly([$sheetInfo['worksheetName']]);
            $reader->setReadFilter(new class($maxRows) implements IReadFilter
            {
                public function __construct(private readonly int $maxRows) {}

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= $this->maxRows
                        && \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnAddress) <= SpreadsheetRowReader::MAX_COLUMNS;
                }
            });

            $sheet = $reader->load($path)->getSheet(0);
        } catch (\Throwable $e) {
            return [
                'rows' => [],
                'total_rows' => 0,
                'error' => 'Could not read the spreadsheet — it may be corrupted or password-protected.',
            ];
        }

        $lastRow = min($sheet->getHighestDataRow(), $maxRows);
        $lastColumn = min(
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()),
            self::MAX_COLUMNS
        );

        $rows = [];

        for ($r = 1; $r <= $lastRow; $r++) {
            $row = [];

            for ($c = 1; $c <= $lastColumn; $c++) {
                $row[] = $sheet->cellExists([$c, $r])
                    ? $this->cellText($sheet->getCell([$c, $r]))
                    : '';
            }

            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'total_rows' => (int) $sheetInfo['totalRows'],
            'error' => null,
        ];
    }

    private function cellText(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): string
    {
        $value = $cell->getDataType() === DataType::TYPE_FORMULA
            ? $cell->getOldCalculatedValue()
            : $cell->getValue();

        return match (true) {
            $value === null => '',
            $value instanceof RichText => $value->getPlainText(),
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_float($value) => $this->floatText($value),
            default => (string) $value,
        };
    }

    /** Plain decimal text, never scientific notation, so toFloat() can read it back. */
    private function floatText(float $value): string
    {
        if ($value == floor($value) && abs($value) < 1e15) {
            return number_format($value, 0, '.', '');
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
