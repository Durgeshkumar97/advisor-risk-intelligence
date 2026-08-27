<?php

namespace App\Services\RiskEngine;

use App\Models\PortfolioFile;
use Illuminate\Support\Facades\Storage;

/**
 * PortfolioParser
 *
 * Parses an uploaded portfolio file (CSV or XLSX) into a normalised
 * array of holding rows ready to be stored as PortfolioAsset records.
 *
 * SUPPORTED FORMATS
 * ─────────────────
 *   CSV  — any delimiter (comma, semicolon, tab auto-detected)
 *   XLSX — parsed via ZipArchive + SimpleXML (no package needed)
 *   PDF  — not supported in V1; returns empty + error flag
 *
 * COLUMN DETECTION
 * ────────────────
 *   Headers are matched case-insensitively using synonym aliases.
 *   Required:   name   +   (current_value  OR  (quantity + current_price))
 *   Optional:   asset_type, symbol, isin, buy_price, invested_value
 *
 * OUTPUT ROW FORMAT
 * ─────────────────
 *   [
 *     'name'           => string,
 *     'asset_type'     => string,   // default 'stock'
 *     'symbol'         => string|null,
 *     'isin'           => string|null,
 *     'quantity'       => float,
 *     'buy_price'      => float,
 *     'current_price'  => float,
 *     'invested_value' => float,
 *     'current_value'  => float,
 *     'profit_loss'    => float,
 *   ]
 */
class PortfolioParser
{
    private const DISK = 'portfolios';

    /*
    |--------------------------------------------------------------------------
    | MAX ROWS
    |--------------------------------------------------------------------------
    |
    | The row loops were previously unbounded. The 20MB upload cap allows a
    | narrow CSV of roughly a million rows, and ProcessPortfolioFile turns each
    | parsed row into a PortfolioAsset insert inside a single transaction —
    | enough to exhaust memory or hit the 300s job timeout on shared hosting.
    |
    | 5,000 is far above any real client portfolio (the largest plan allows
    | 1,000 clients a MONTH, one file each) while bounding worst-case work.
    | This is separate from ProcessPortfolioFile's cap of 500 DISTINCT STOCK
    | SYMBOLS, which bounds the outbound classifier batch rather than parse or
    | insert volume; the two limits do not overlap and neither subsumes the
    | other.
    |
    | Exceeding it REJECTS the file rather than truncating. Scoring the first
    | 5,000 rows of a larger portfolio would silently produce a confident,
    | wrong composite from partial holdings — worse than a clear failure.
    |
    */

    private const MAX_ROWS = 5000;

    /**
     * Raw asset_type values this parse could not recognise, keyed by the raw
     * value with a holding count. Reset per parse() call — see buildWarnings().
     *
     * @var array<string, int>
     */
    private array $unknownAssetTypes = [];

    public const MAX_ROWS_MESSAGE = 'File exceeds maximum of 5,000 rows. Please reduce the file size and re-upload.';

    /*
    |--------------------------------------------------------------------------
    | COLUMN ALIASES
    |--------------------------------------------------------------------------
    |
    | Each internal field maps to a list of header strings we accept.
    | First match in the CSV header row wins.
    |
    */

    private const ALIASES = [
        'name' => [
            'name', 'asset name', 'scheme name', 'fund name', 'stock name',
            'security name', 'security', 'company', 'instrument',
            'scrip name', 'description', 'scheme',
        ],
        'asset_type' => [
            'asset_type', 'asset type', 'type', 'asset class',
            'category', 'instrument type', 'product type',
        ],
        'symbol' => [
            'symbol', 'ticker', 'tradingsymbol', 'trading symbol',
            'nse symbol', 'bse code', 'scrip code', 'stock code',
        ],
        'isin' => [
            'isin', 'isin code', 'isin number',
        ],
        'quantity' => [
            'quantity', 'qty', 'units', 'shares', 'no. of units',
            'no of units', 'holding units', 'balance units',
        ],
        'buy_price' => [
            'buy_price', 'buy price', 'avg price', 'average price',
            'purchase price', 'cost price', 'avg cost', 'average cost',
            'nav at purchase', 'purchase nav',
        ],
        'current_price' => [
            'current_price', 'current price', 'ltp', 'last price',
            'nav', 'current nav', 'market price', 'cmp', 'close price',
        ],
        'invested_value' => [
            'invested_value', 'invested amount', 'purchase value',
            'cost value', 'amount invested', 'invested', 'cost',
            'purchase amount', 'book value',
        ],
        'current_value' => [
            'current_value', 'current amount', 'market value',
            'present value', 'value', 'current value', 'portfolio value',
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | ASSET TYPE NORMALISATION
    |--------------------------------------------------------------------------
    */

    private const ASSET_TYPE_MAP = [
        'stock' => ['stock', 'equity', 'share', 'shares', 'eq'],
        'mutual_fund' => ['mutual fund', 'mf', 'mutual_fund', 'fund'],
        'etf' => ['etf', 'exchange traded fund'],
        'bond' => ['bond', 'ncd', 'debenture', 'sgb', 'sovereign gold bond', 'gilt', 'g-sec',
            'fixed deposit', 'fd', 'ppf', 'public provident fund',
            'treasury', 't-bill', 'tbill', 'nsc', 'kisan vikas',
            'debt', 'fixed income', 'debt fund'],
        'commodity' => ['commodity', 'gold', 'silver', 'commodity etf'],
        'foreign_stock' => ['foreign stock', 'us stock', 'global stock', 'international'],
        'crypto' => ['crypto', 'cryptocurrency', 'bitcoin', 'btc', 'ethereum'],
        'cash' => ['cash', 'liquid', 'liquid fund', 'overnight'],
    ];

    /*
    |--------------------------------------------------------------------------
    | PARSE
    |--------------------------------------------------------------------------
    */

    /**
     * `warnings` are non-fatal notes about how the file was interpreted — the
     * parse still succeeded. `errors` remain the things that stopped a row (or
     * the whole file) being used.
     *
     * @return array{rows: array, errors: array, warnings: array, count: int}
     */
    public function parse(PortfolioFile $portfolioFile): array
    {
        // Reset per call — this service can be resolved once and reused for
        // several files in the same worker process.
        $this->unknownAssetTypes = [];

        $path = Storage::disk(self::DISK)->path($portfolioFile->path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $result = match ($ext) {
            'csv' => $this->parseCSV($path),
            'xlsx', 'xls' => $this->parseXLSX($path),
            default => [
                'rows' => [],
                'errors' => ["File type .{$ext} is not supported. Please upload a CSV or XLSX file."],
                'count' => 0,
            ],
        };

        return $result + ['warnings' => $this->buildWarnings()];
    }

    /**
     * One human-readable line per unrecognised asset_type, naming the value and
     * how many holdings it covered.
     *
     * @return list<string>
     */
    private function buildWarnings(): array
    {
        $warnings = [];

        foreach ($this->unknownAssetTypes as $raw => $count) {
            $warnings[] = sprintf(
                'Unrecognised asset type "%s" on %d holding%s — scored as equity. Supported types: %s.',
                $raw,
                $count,
                $count === 1 ? '' : 's',
                implode(', ', array_keys(self::ASSET_TYPE_MAP)),
            );
        }

        return $warnings;
    }

    /*
    |--------------------------------------------------------------------------
    | CSV PARSER
    |--------------------------------------------------------------------------
    */

    private function parseCSV(string $path): array
    {
        $rows = [];
        $errors = [];

        $handle = @fopen($path, 'r');
        if (! $handle) {
            return ['rows' => [], 'errors' => ['Could not open file for reading.'], 'count' => 0];
        }

        // Auto-detect delimiter from first line
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = $this->detectDelimiter($firstLine);

        // Read header
        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (! $rawHeaders) {
            fclose($handle);

            return ['rows' => [], 'errors' => ['File appears to be empty.'], 'count' => 0];
        }

        $headerMap = $this->buildHeaderMap($rawHeaders);

        if (! isset($headerMap['name'])) {
            fclose($handle);

            return ['rows' => [], 'errors' => ['Could not find a "name" column. Please include a column with the holding name.'], 'count' => 0];
        }

        $lineNum = 1;
        while (($rawRow = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNum++;

            // Skip blank rows
            $cleaned = array_filter($rawRow, fn ($v) => trim($v) !== '');
            if (empty($cleaned)) {
                continue;
            }

            // Reject, don't truncate — see MAX_ROWS. Checked before mapRow so
            // an oversized file costs no further per-row work.
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return ['rows' => [], 'errors' => [self::MAX_ROWS_MESSAGE], 'count' => 0];
            }

            $row = $this->mapRow($rawRow, $headerMap);

            if ($row === null) {
                $errors[] = "Row {$lineNum}: skipped (missing required data).";

                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors, 'count' => count($rows)];
    }

    /*
    |--------------------------------------------------------------------------
    | XLSX PARSER  (ZipArchive + SimpleXML — no package needed)
    |--------------------------------------------------------------------------
    */

    private function parseXLSX(string $path): array
    {
        if (! class_exists('ZipArchive')) {
            return [
                'rows' => [],
                'errors' => ['ZipArchive extension is not available on this server. Please upload a CSV instead.'],
                'count' => 0,
            ];
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return ['rows' => [], 'errors' => ['Could not read XLSX file — it may be corrupted.'], 'count' => 0];
        }

        // Load shared strings (text values in XLSX are stored here)
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                // Concatenate all <t> elements (handles rich text)
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
                if ($text === '') {
                    $text = (string) $si->t;
                }
                $sharedStrings[] = $text;
            }
        }

        // Load the first worksheet
        $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($worksheetXml === false) {
            return ['rows' => [], 'errors' => ['Could not read worksheet from XLSX file.'], 'count' => 0];
        }

        $ws = simplexml_load_string($worksheetXml);
        $data = [];

        foreach ($ws->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $type = (string) $cell['t'];
                $value = (string) $cell->v;

                if ($type === 's') {
                    // Shared string reference
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) $cell->is->t;
                }

                $rowData[] = $value;
            }
            $data[] = $rowData;
        }

        if (empty($data)) {
            return ['rows' => [], 'errors' => ['Worksheet is empty.'], 'count' => 0];
        }

        // First row = headers
        $rawHeaders = array_shift($data);
        $headerMap = $this->buildHeaderMap($rawHeaders);

        if (! isset($headerMap['name'])) {
            return ['rows' => [], 'errors' => ['Could not find a "name" column. Please check the file format.'], 'count' => 0];
        }

        $rows = [];
        $errors = [];
        $lineNum = 1;

        foreach ($data as $rawRow) {
            $lineNum++;
            $cleaned = array_filter($rawRow, fn ($v) => trim($v) !== '');
            if (empty($cleaned)) {
                continue;
            }

            // Reject, don't truncate — see MAX_ROWS.
            if (count($rows) >= self::MAX_ROWS) {
                return ['rows' => [], 'errors' => [self::MAX_ROWS_MESSAGE], 'count' => 0];
            }

            $row = $this->mapRow($rawRow, $headerMap);
            if ($row === null) {
                $errors[] = "Row {$lineNum}: skipped (missing required data).";

                continue;
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors, 'count' => count($rows)];
    }

    /*
    |--------------------------------------------------------------------------
    | MAP ONE ROW
    |--------------------------------------------------------------------------
    */

    /**
     * Map a raw CSV/XLSX row to a normalised holding array.
     * Returns null if the row cannot produce a valid holding.
     */
    private function mapRow(array $rawRow, array $headerMap): ?array
    {
        $get = function (string $field) use ($rawRow, $headerMap): string {
            $idx = $headerMap[$field] ?? null;

            return ($idx !== null && isset($rawRow[$idx])) ? trim((string) $rawRow[$idx]) : '';
        };

        // Required: name
        $name = $get('name');
        if ($name === '') {
            return null;
        }

        // Asset type (normalised)
        $assetType = $this->normaliseAssetType($get('asset_type'), $name);

        // Values — derive what we can
        $quantity = $this->toFloat($get('quantity'));
        $buyPrice = $this->toFloat($get('buy_price'));
        $currentPrice = $this->toFloat($get('current_price'));
        $investedValue = $this->toFloat($get('invested_value'));
        $currentValue = $this->toFloat($get('current_value'));

        // Derive current_value from quantity × current_price if not given
        if ($currentValue <= 0 && $quantity > 0 && $currentPrice > 0) {
            $currentValue = round($quantity * $currentPrice, 2);
        }

        // Derive invested_value from quantity × buy_price if not given
        if ($investedValue <= 0 && $quantity > 0 && $buyPrice > 0) {
            $investedValue = round($quantity * $buyPrice, 2);
        }

        // Must have a current value to be meaningful
        if ($currentValue <= 0) {
            return null;
        }

        // Derive buy price from invested / quantity if still zero
        if ($buyPrice <= 0 && $quantity > 0 && $investedValue > 0) {
            $buyPrice = round($investedValue / $quantity, 2);
        }

        // Derive current price if still zero
        if ($currentPrice <= 0 && $quantity > 0 && $currentValue > 0) {
            $currentPrice = round($currentValue / $quantity, 2);
        }

        $profitLoss = round($currentValue - $investedValue, 2);

        return [
            'name' => $name,
            'asset_type' => $assetType,
            'symbol' => $get('symbol') ?: null,
            'isin' => $get('isin') ?: null,
            'quantity' => $quantity,
            'buy_price' => $buyPrice,
            'current_price' => $currentPrice,
            'invested_value' => $investedValue,
            'current_value' => $currentValue,
            'profit_loss' => $profitLoss,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HEADER MAP BUILDER
    |--------------------------------------------------------------------------
    */

    /**
     * Returns ['field_name' => column_index] for all found aliases.
     */
    private function buildHeaderMap(array $rawHeaders): array
    {
        $normalised = array_map(fn ($h) => strtolower(trim($h)), $rawHeaders);
        $map = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $normalised, true);
                if ($idx !== false) {
                    $map[$field] = $idx;
                    break;
                }
            }
        }

        return $map;
    }

    /*
    |--------------------------------------------------------------------------
    | ASSET TYPE NORMALISER
    |--------------------------------------------------------------------------
    */

    private function normaliseAssetType(string $raw, string $name = ''): string
    {
        if ($raw === '') {
            // No explicit asset_type column — try to infer from the holding name
            return $this->normaliseAssetType($name === '' ? 'stock' : $name);
        }

        $lower = strtolower(trim($raw));

        foreach (self::ASSET_TYPE_MAP as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($lower, $alias)) {
                    return $canonical;
                }
            }
        }

        /*
        | Nothing matched, so this falls through to 'stock' below and is scored
        | at 65. That is a defensible default — but silently calling someone's
        | real-estate or insurance holding an equity, with no trace, is not.
        |
        | Recorded here rather than in AssetRiskScorer: by the time the scorer
        | runs, the type has already been rewritten to 'stock', so its own
        | unknown-type branch is unreachable from this path and flagging it
        | there would change nothing observable.
        */
        $this->unknownAssetTypes[$lower] = ($this->unknownAssetTypes[$lower] ?? 0) + 1;

        return 'stock'; // safe default
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function detectDelimiter(string $line): string
    {
        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
            '|' => substr_count($line, '|'),
        ];
        arsort($counts);

        return array_key_first($counts);
    }

    private function toFloat(string $value): float
    {
        // Remove currency symbols, commas, spaces
        $cleaned = preg_replace('/[₹$£€,\s]/', '', $value);

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }
}
