<?php

namespace App\Services\RiskEngine;

use App\Models\PortfolioFile;
use Illuminate\Support\Facades\Storage;

/**
 * PortfolioParser
 *
 * Parses an uploaded portfolio file (CSV, XLSX or XLS) into a normalised
 * array of holding rows ready to be stored as PortfolioAsset records.
 *
 * SUPPORTED FORMATS
 * ─────────────────
 *   CSV  — comma, semicolon, tab or pipe delimited (auto-detected); a UTF-8
 *          byte-order mark is stripped
 *   XLSX / XLS — read with PhpSpreadsheet (SpreadsheetRowReader), first
 *          worksheet only. The format is identified from the file's content,
 *          so an .xls that is really OOXML (or the reverse) still reads.
 *   PDF  — not supported; returns an error naming the supported formats
 *
 * HEADER DETECTION
 * ────────────────
 *   Broker exports put title blocks above the table (Groww: header on row 10,
 *   INDmoney: row 8). The first 25 rows are scored by how many distinct
 *   fields their cells match (see ALIASES); the highest-scoring row with at
 *   least 2 matches is the header, and on a tie the EARLIEST row wins — so a
 *   CSV whose row 1 is a valid header can never lose to a later row. Header
 *   cells are matched case-insensitively after trimming, collapsing
 *   whitespace and stripping a trailing currency marker such as "($)".
 *
 * ROWS AFTER THE HEADER
 * ─────────────────────
 *   Skipped silently: blank rows (they never end parsing), total rows (first
 *   cell exactly "total" / "grand total" / "sub total", optional colon),
 *   rows starting "disclaimer" or "*", and text-only rows (a name but no
 *   numbers — e.g. a disclaimer paragraph). A row WITH numbers but no name,
 *   or whose values cannot be resolved, is skipped and reported as an error.
 *
 * FILE-LEVEL REJECTIONS
 * ─────────────────────
 *   No header, values in US dollars (never converted, never read as rupees),
 *   no source of current market value, or more than MAX_ROWS holdings.
 *
 * UNKNOWN IS NOT ZERO
 * ───────────────────
 *   invested_value is taken from the file, else derived as quantity × a COST
 *   price (e.g. "Avg. Price"), else left null. A market price (NAV, LTP) is
 *   never used to derive cost — that would make P&L structurally zero.
 *   profit_loss is null whenever invested_value is. invested_value_source
 *   records which of file / derived / unknown applied.
 *
 * OUTPUT ROW FORMAT
 * ─────────────────
 *   [
 *     'name'                  => string,
 *     'asset_type'            => string,   // default 'stock'
 *     'symbol'                => string|null,
 *     'isin'                  => string|null,
 *     'quantity'              => float,
 *     'buy_price'             => float,
 *     'current_price'         => float,
 *     'invested_value'        => float|null,
 *     'current_value'         => float,
 *     'profit_loss'           => float|null,
 *     'invested_value_source' => 'file'|'derived'|'unknown',
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
    | 5,000 holdings is far above any real client portfolio while bounding
    | worst-case work (ProcessPortfolioFile inserts one PortfolioAsset per row
    | inside a single transaction). Exceeding it REJECTS the file rather than
    | truncating: scoring the first 5,000 rows of a larger portfolio would
    | silently produce a confident, wrong composite from partial holdings.
    |
    | Readers materialise at most HEADER_SCAN_ROWS + MAX_ROWS + JUNK_ROW_ALLOWANCE
    | rows; a sheet taller than that is rejected before any row is mapped.
    |
    */

    private const MAX_ROWS = 5000;

    private const HEADER_SCAN_ROWS = 25;

    private const MIN_HEADER_MATCHES = 2;

    /** Room for blank, total and disclaimer rows around a full-size table. */
    private const JUNK_ROW_ALLOWANCE = 200;

    private const READ_ROW_CAP = self::HEADER_SCAN_ROWS + self::MAX_ROWS + self::JUNK_ROW_ALLOWANCE;

    public const MAX_ROWS_MESSAGE = 'File exceeds maximum of 5,000 rows. Please reduce the file size and re-upload.';

    public const NO_HEADER_MESSAGE = 'Could not find the column headers in the first 25 rows. RiskSignal needs a holding-name column (e.g. Name, Fund Name, Scheme Name, Stock Symbol) and a value column (e.g. Current Value, Market Value, or Quantity with a current price or NAV).';

    public const NO_MARKET_VALUE_MESSAGE = 'This file has no current market value for its holdings. RiskSignal needs a Current Value or Market Value column, or Quantity with a current price or NAV.';

    public const USD_MESSAGE = 'This export\'s values are in US dollars (%d holdings found). RiskSignal scores portfolios in Indian rupees; please upload an INR export.';

    public const UNSUPPORTED_MESSAGE = 'File type .%s is not supported. Supported: CSV, XLSX, XLS, or a ZIP of these.';

    private const SUPPORTED_EXTENSIONS = ['csv', 'xlsx', 'xls'];

    private const OOXML_MAGIC = "PK\x03\x04";

    private const BIFF_MAGIC = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    private const UTF8_BOM = "\xEF\xBB\xBF";

    /** Fields whose presence marks a row as carrying data (not just text). */
    private const NUMERIC_FIELDS = ['quantity', 'buy_price', 'current_price', 'invested_value', 'current_value'];

    /**
     * Raw asset_type values this parse could not recognise, keyed by the raw
     * value with a holding count. Reset per parse() call — see buildWarnings().
     *
     * @var array<string, int>
     */
    private array $unknownAssetTypes = [];

    public function __construct(
        private readonly SpreadsheetRowReader $spreadsheetReader = new SpreadsheetRowReader,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | COLUMN ALIASES
    |--------------------------------------------------------------------------
    |
    | Each internal field maps to the header strings we accept, compared after
    | normaliseHeader(). The first alias found in the header row wins.
    |
    | buy_price is a COST price (what was paid per unit) and is the only price
    | used to derive invested_value. current_price is a MARKET price and is
    | used only to derive current_value.
    |
    */

    private const ALIASES = [
        'name' => [
            'name', 'asset name', 'scheme name', 'fund name', 'stock name',
            'security name', 'security', 'company', 'instrument',
            'scrip name', 'description', 'scheme',
            'stock symbol',                                    // INDmoney US stocks
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
            'total units',                                     // Groww mutual funds
        ],
        'buy_price' => [
            'buy_price', 'buy price', 'avg price', 'average price',
            'purchase price', 'cost price', 'avg cost', 'average cost',
            'nav at purchase', 'purchase nav',
            'avg. price',                                      // INDmoney US stocks
        ],
        'current_price' => [
            'current_price', 'current price', 'ltp', 'last price',
            'nav', 'current nav', 'market price', 'cmp', 'close price',
        ],
        'invested_value' => [
            'invested_value', 'invested amount', 'purchase value',
            'cost value', 'amount invested', 'invested', 'cost',
            'purchase amount', 'book value',
            'invested value',                                  // Groww mutual funds
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

        if (! in_array($ext, self::SUPPORTED_EXTENSIONS, true)) {
            return $this->failure(sprintf(self::UNSUPPORTED_MESSAGE, $ext)) + ['warnings' => []];
        }

        $read = $this->readRows($path);

        $result = $read['error'] !== null
            ? $this->failure($read['error'])
            : $this->parseRows($read['rows']);

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
    | READING — raw rows of strings, routed by content
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{rows: list<list<string>>, error: ?string}
     */
    private function readRows(string $path): array
    {
        $head = @file_get_contents($path, false, null, 0, 8);

        if ($head === false) {
            return ['rows' => [], 'error' => 'Could not open file for reading.'];
        }

        if (str_starts_with($head, self::OOXML_MAGIC) || str_starts_with($head, self::BIFF_MAGIC)) {
            $read = $this->spreadsheetReader->read($path, self::READ_ROW_CAP);

            if ($read['error'] === null && $read['total_rows'] > self::READ_ROW_CAP) {
                return ['rows' => [], 'error' => self::MAX_ROWS_MESSAGE];
            }

            return ['rows' => $read['rows'], 'error' => $read['error']];
        }

        return $this->readCsvRows($path);
    }

    /**
     * @return array{rows: list<list<string>>, error: ?string}
     */
    private function readCsvRows(string $path): array
    {
        $handle = @fopen($path, 'r');

        if (! $handle) {
            return ['rows' => [], 'error' => 'Could not open file for reading.'];
        }

        // Delimiter from the first lines together: a title line above the
        // header usually has no delimiters at all.
        $sample = '';
        for ($i = 0; $i < self::HEADER_SCAN_ROWS && ($line = fgets($handle)) !== false; $i++) {
            $sample .= $line;
        }
        $delimiter = $this->detectDelimiter($sample);

        // Skip a UTF-8 byte-order mark (Excel's "CSV UTF-8" export).
        rewind($handle);
        if (fread($handle, 3) !== self::UTF8_BOM) {
            rewind($handle);
        }

        $rows = [];

        while (($record = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($rows) >= self::READ_ROW_CAP) {
                fclose($handle);

                return ['rows' => [], 'error' => self::MAX_ROWS_MESSAGE];
            }

            $rows[] = array_map(fn ($value) => (string) ($value ?? ''), $record);
        }

        fclose($handle);

        return ['rows' => $rows, 'error' => null];
    }

    /*
    |--------------------------------------------------------------------------
    | PARSING — one pipeline for every format
    |--------------------------------------------------------------------------
    */

    /**
     * @param  list<list<string>>  $rawRows
     * @return array{rows: array, errors: array, count: int}
     */
    private function parseRows(array $rawRows): array
    {
        $isBlankRow = fn (array $row) => implode('', array_map('trim', $row)) === '';

        if (collect($rawRows)->reject($isBlankRow)->isEmpty()) {
            return $this->failure('File appears to be empty.');
        }

        $header = $this->detectHeader($rawRows);

        if ($header === null || ! isset($header['map']['name'])) {
            return $this->failure(self::NO_HEADER_MESSAGE);
        }

        $headerMap = $header['map'];

        $hasMarketValue = isset($headerMap['current_value'])
            || (isset($headerMap['quantity']) && isset($headerMap['current_price']));

        $rows = [];
        $errors = [];
        $candidateCount = 0;

        foreach (array_slice($rawRows, $header['index'] + 1, null, true) as $index => $rawRow) {
            $lineNum = $index + 1;

            if ($isBlankRow($rawRow) || $this->isJunkRow($rawRow)) {
                continue;
            }

            $name = $this->cell($rawRow, $headerMap, 'name');
            $hasNumbers = collect(self::NUMERIC_FIELDS)
                ->contains(fn ($field) => $this->cell($rawRow, $headerMap, $field) !== '');

            if (! $hasNumbers) {
                // Text-only (disclaimer paragraph, section label): not a holding.
                continue;
            }

            if ($name === '') {
                $errors[] = "Row {$lineNum}: skipped (missing required data).";

                continue;
            }

            $candidateCount++;

            if ($header['usd'] || ! $hasMarketValue) {
                // File-level rejection follows; only count what was found.
                continue;
            }

            // Reject, don't truncate — see MAX_ROWS.
            if (count($rows) >= self::MAX_ROWS) {
                return $this->failure(self::MAX_ROWS_MESSAGE);
            }

            $row = $this->mapRow($rawRow, $headerMap);

            if ($row === null) {
                $errors[] = "Row {$lineNum}: skipped (missing required data).";

                continue;
            }

            $rows[] = $row;
        }

        if ($header['usd']) {
            return $this->failure(sprintf(self::USD_MESSAGE, $candidateCount));
        }

        if (! $hasMarketValue) {
            return $this->failure(self::NO_MARKET_VALUE_MESSAGE);
        }

        return ['rows' => $rows, 'errors' => $errors, 'count' => count($rows)];
    }

    /**
     * @return array{rows: array, errors: array, count: int}
     */
    private function failure(string $message): array
    {
        return ['rows' => [], 'errors' => [$message], 'count' => 0];
    }

    /*
    |--------------------------------------------------------------------------
    | HEADER DETECTION
    |--------------------------------------------------------------------------
    */

    /**
     * Highest-scoring row among the first HEADER_SCAN_ROWS with at least
     * MIN_HEADER_MATCHES distinct fields; ties go to the EARLIEST row.
     *
     * @param  list<list<string>>  $rawRows
     * @return array{index: int, map: array<string, int>, usd: bool}|null
     */
    private function detectHeader(array $rawRows): ?array
    {
        $best = null;

        foreach (array_slice($rawRows, 0, self::HEADER_SCAN_ROWS) as $index => $cells) {
            $map = $this->buildHeaderMap($cells);

            if (count($map) < self::MIN_HEADER_MATCHES) {
                continue;
            }

            // Strictly greater: an equal score never displaces an earlier row.
            if ($best === null || count($map) > count($best['map'])) {
                $best = ['index' => $index, 'map' => $map, 'usd' => $this->declaresUsd($cells)];
            }
        }

        return $best;
    }

    /**
     * Returns ['field_name' => column_index] for all found aliases.
     *
     * @param  list<string>  $rawHeaders
     * @return array<string, int>
     */
    private function buildHeaderMap(array $rawHeaders): array
    {
        $normalised = array_map(fn ($h) => $this->normaliseHeader((string) $h), $rawHeaders);
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

    private function normaliseHeader(string $header): string
    {
        $header = str_starts_with($header, self::UTF8_BOM) ? substr($header, 3) : $header;
        $header = mb_strtolower(trim($header));
        $header = preg_replace('/\s+/u', ' ', $header);
        $header = preg_replace('/\s*\((\$|₹|inr|rs\.?|usd)\)$/u', '', $header);

        return trim($header);
    }

    /** A header cell carrying a "($)" / "(USD)" marker or the word USD. */
    private function declaresUsd(array $cells): bool
    {
        foreach ($cells as $cell) {
            $cell = mb_strtolower((string) $cell);

            if (str_contains($cell, '($)') || preg_match('/\busd\b/u', $cell)) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | JUNK ROWS
    |--------------------------------------------------------------------------
    */

    /**
     * Total rows match the first non-empty cell EXACTLY (so a holding named
     * "Total Market Fund" is kept); disclaimer and footnote rows match by prefix.
     */
    private function isJunkRow(array $rawRow): bool
    {
        $first = '';
        foreach ($rawRow as $cell) {
            if (trim((string) $cell) !== '') {
                $first = mb_strtolower(trim((string) $cell));
                break;
            }
        }

        $label = rtrim($first, ': ');

        return in_array($label, ['total', 'grand total', 'sub total'], true)
            || str_starts_with($first, 'disclaimer')
            || str_starts_with($first, '*');
    }

    /*
    |--------------------------------------------------------------------------
    | MAP ONE ROW
    |--------------------------------------------------------------------------
    */

    private function cell(array $rawRow, array $headerMap, string $field): string
    {
        $idx = $headerMap[$field] ?? null;

        return ($idx !== null && isset($rawRow[$idx])) ? trim((string) $rawRow[$idx]) : '';
    }

    /**
     * Map a raw row to a normalised holding array.
     * Returns null if the row cannot produce a valid holding.
     */
    private function mapRow(array $rawRow, array $headerMap): ?array
    {
        $get = fn (string $field): string => $this->cell($rawRow, $headerMap, $field);

        $name = $get('name');
        if ($name === '') {
            return null;
        }

        $assetType = $this->normaliseAssetType($get('asset_type'), $name);

        $quantity = $this->toFloat($get('quantity'));
        $buyPrice = $this->toFloat($get('buy_price'));
        $currentPrice = $this->toFloat($get('current_price'));
        $currentValue = $this->toFloat($get('current_value'));

        // Derive current_value from quantity × MARKET price if not given
        if ($currentValue <= 0 && $quantity > 0 && $currentPrice > 0) {
            $currentValue = round($quantity * $currentPrice, 2);
        }

        // Must have a current value to be meaningful
        if ($currentValue <= 0) {
            return null;
        }

        // Cost basis: from the file, else quantity × COST price, else unknown.
        // Never zero-by-default: 0 would read as +100% profit and hide losses.
        $investedCell = $get('invested_value');
        $investedFromFile = is_numeric(preg_replace('/[₹$£€,\s]/', '', $investedCell))
            ? $this->toFloat($investedCell)
            : null;

        if ($investedFromFile !== null && $investedFromFile > 0) {
            [$investedValue, $investedSource] = [$investedFromFile, 'file'];
        } elseif ($quantity > 0 && $buyPrice > 0) {
            [$investedValue, $investedSource] = [round($quantity * $buyPrice, 2), 'derived'];
        } elseif ($investedFromFile !== null) {
            [$investedValue, $investedSource] = [$investedFromFile, 'file'];
        } else {
            [$investedValue, $investedSource] = [null, 'unknown'];
        }

        // Derive buy price from invested / quantity if still zero
        if ($buyPrice <= 0 && $quantity > 0 && $investedValue !== null && $investedValue > 0) {
            $buyPrice = round($investedValue / $quantity, 2);
        }

        // Derive current price if still zero
        if ($currentPrice <= 0 && $quantity > 0 && $currentValue > 0) {
            $currentPrice = round($currentValue / $quantity, 2);
        }

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
            'profit_loss' => $investedValue === null ? null : round($currentValue - $investedValue, 2),
            'invested_value_source' => $investedSource,
        ];
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
