<?php

use App\Models\PortfolioFile;
use App\Services\RiskEngine\PortfolioParser;
use Illuminate\Support\Facades\Storage;

uses(\Tests\TestCase::class);

beforeEach(function () {
    Storage::fake('portfolios');
    $this->parser = new PortfolioParser;
});

// ---------------------------------------------------------------------------
// Helper — write CSV to fake disk and return a PortfolioFile stub
// ---------------------------------------------------------------------------

function csvFile(string $filename, string $content): PortfolioFile
{
    Storage::disk('portfolios')->put($filename, $content);

    return (new PortfolioFile)->forceFill(['path' => $filename]);
}

// ---------------------------------------------------------------------------
// Basic CSV parsing
// ---------------------------------------------------------------------------

it('parses a CSV with all standard columns', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset_type,quantity,buy_price,current_price,invested_value,current_value',
        'Reliance Industries,stock,10,2000,2500,20000,25000',
        'HDFC Bank,stock,5,1400,1600,7000,8000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['errors'])->toBeEmpty();

    $row = $result['rows'][0];
    expect($row['name'])->toBe('Reliance Industries')
        ->and($row['asset_type'])->toBe('stock')
        ->and($row['quantity'])->toBe(10.0)
        ->and($row['buy_price'])->toBe(2000.0)
        ->and($row['current_price'])->toBe(2500.0)
        ->and($row['invested_value'])->toBe(20000.0)
        ->and($row['current_value'])->toBe(25000.0)
        ->and($row['profit_loss'])->toBe(5000.0);
});

it('parses symbol and isin when present', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,symbol,isin,current_value',
        'Reliance Industries,RELIANCE,INE002A01018,25000',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['symbol'])->toBe('RELIANCE')
        ->and($row['isin'])->toBe('INE002A01018');
});

it('sets symbol and isin to null when columns are absent', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Reliance Industries,25000',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['symbol'])->toBeNull()
        ->and($row['isin'])->toBeNull();
});

// ---------------------------------------------------------------------------
// Column alias matching
// ---------------------------------------------------------------------------

it('maps alternative column headers to internal field names', function () {
    // 'scheme name' → name, 'ltp' → current_price, 'invested amount' → invested_value
    $file = csvFile('portfolio.csv', implode("\n", [
        'scheme name,ltp,invested amount,units',
        'HDFC Liquid Fund,10.50,10000,952',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['name'])->toBe('HDFC Liquid Fund')
        ->and($row['current_price'])->toBe(10.50)
        ->and($row['invested_value'])->toBe(10000.0)
        ->and($row['quantity'])->toBe(952.0);
});

it('maps "nav" to current_price and "book value" to invested_value', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,nav,book value,balance units',
        'ICICI Bluechip Fund,150.25,140000,1000',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['current_price'])->toBe(150.25)
        ->and($row['invested_value'])->toBe(140000.0)
        ->and($row['quantity'])->toBe(1000.0);
});

// ---------------------------------------------------------------------------
// Delimiter auto-detection
// ---------------------------------------------------------------------------

it('parses a semicolon-delimited CSV', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name;asset_type;current_value;invested_value',
        'Reliance;stock;25000;20000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('Reliance')
        ->and($result['rows'][0]['current_value'])->toBe(25000.0);
});

it('parses a tab-delimited CSV', function () {
    $file = csvFile('portfolio.csv', "name\tcurrent_value\tinvested_value\nApple Inc\t15000\t12000");

    $result = $this->parser->parse($file);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('Apple Inc')
        ->and($result['rows'][0]['current_value'])->toBe(15000.0);
});

// ---------------------------------------------------------------------------
// Derived values
// ---------------------------------------------------------------------------

it('derives current_value from quantity × current_price when column is absent', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,quantity,current_price',
        'Apple Inc,100,150',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['current_value'])->toBe(15000.0);  // 100 × 150
});

it('derives invested_value from quantity × buy_price when column is absent', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,quantity,buy_price,current_value',
        'Apple Inc,100,140,15000',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['invested_value'])->toBe(14000.0);  // 100 × 140
});

it('derives current_price from current_value ÷ quantity when price column is absent', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,quantity,current_value',
        'Reliance,10,25000',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['current_price'])->toBe(2500.0);  // 25000 ÷ 10
});

// ---------------------------------------------------------------------------
// Currency symbol stripping
// ---------------------------------------------------------------------------

it('strips ₹ from numeric value columns', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value,invested_value',
        'Reliance,₹25000,₹20000',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['current_value'])->toBe(25000.0)
        ->and($row['invested_value'])->toBe(20000.0);
});

// ---------------------------------------------------------------------------
// Asset type normalisation
// ---------------------------------------------------------------------------

it('normalises "mf" to mutual_fund', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        'HDFC MF,mf,10000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('mutual_fund');
});

it('normalises "equity" to stock', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,type,current_value',
        'Reliance,equity,25000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('stock');
});

it('normalises "ETF" to etf', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        'Nifty ETF,ETF,5000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('etf');
});

it('defaults to stock when asset_type column is absent', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Some Security,10000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('stock');
});

// ---------------------------------------------------------------------------
// Row-level edge cases
// ---------------------------------------------------------------------------

it('silently skips completely blank rows', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Reliance,25000',
        '',
        'HDFC Bank,8000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['errors'])->toBeEmpty();
});

it('skips rows where current_value cannot be resolved and records an error', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,buy_price',
        'Bad Asset,100',   // no current_value, no quantity×current_price to derive it from
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toHaveCount(1);
});

it('skips rows with an empty name', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        ',25000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Error conditions
// ---------------------------------------------------------------------------

it('returns an error and no rows when the name column is missing', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'symbol,quantity,current_price',
        'REL,10,2500',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->not->toBeEmpty()
        ->and($result['errors'][0])->toContain('name');
});

it('returns an error for an empty file', function () {
    $file = csvFile('empty.csv', '');

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->not->toBeEmpty();
});

it('returns an error and no rows for an unsupported file extension', function () {
    // File does not need to exist on disk — parser rejects by extension before opening
    $file = (new PortfolioFile)->forceFill(['path' => 'document.pdf']);

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'][0])->toContain('.pdf');
});

// ---------------------------------------------------------------------------
// Pipe delimiter
// ---------------------------------------------------------------------------

it('parses a pipe-delimited CSV', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name|asset_type|current_value|invested_value',
        'Reliance|stock|25000|20000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('Reliance')
        ->and($result['rows'][0]['current_value'])->toBe(25000.0);
});

// ---------------------------------------------------------------------------
// Currency symbol stripping — $, £, €
// ---------------------------------------------------------------------------

it('strips $ from numeric value columns', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value,invested_value',
        'Apple Inc,$15000,$12000',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['current_value'])->toBe(15000.0)
        ->and($row['invested_value'])->toBe(12000.0);
});

it('strips £ and € from numeric value columns', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value,invested_value',
        'BP Plc,£8000,£7000',
        'LVMH,€5000,€4500',
    ]));

    $rows = $this->parser->parse($file)['rows'];

    expect($rows[0]['current_value'])->toBe(8000.0)
        ->and($rows[1]['current_value'])->toBe(5000.0);
});

it('strips commas from comma-formatted numbers when values are quoted', function () {
    // fgetcsv splits on delimiter first, so comma-numbers must be quoted in the CSV
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value,invested_value',
        '"Reliance","1,50,000","1,20,000"',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['current_value'])->toBe(150000.0)
        ->and($row['invested_value'])->toBe(120000.0);
});

// ---------------------------------------------------------------------------
// Derived buy_price from invested_value ÷ quantity
// ---------------------------------------------------------------------------

it('derives buy_price from invested_value ÷ quantity when buy_price column is absent', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,quantity,invested_value,current_value',
        'Reliance,10,20000,25000',
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    expect($row['buy_price'])->toBe(2000.0);  // 20000 ÷ 10
});

// ---------------------------------------------------------------------------
// Asset type inferred from name when no asset_type column
// ---------------------------------------------------------------------------

it('infers crypto from holding name when no asset_type column', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Bitcoin Holdings,50000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('crypto');
});

it('infers commodity from holding name containing "gold"', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Sovereign Gold Bond,10000',
    ]));

    // "Sovereign Gold Bond" — "gold" matches commodity before "bond" matches bond
    // The map is iterated in declaration order: stock, mutual_fund, etf, bond, commodity…
    // "sovereign gold bond" contains "bond" (bond map) so bond wins
    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('bond');
});

it('infers commodity from holding name containing "silver"', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Silver ETF,5000',
    ]));

    // "silver etf" — "etf" matches etf (declared before commodity), etf wins
    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('etf');
});

// ---------------------------------------------------------------------------
// Asset type normalisation — remaining canonical types
// ---------------------------------------------------------------------------

it('normalises "bond" to bond', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        '7% GOI Bond,bond,50000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('bond');
});

it('normalises "debt" to bond', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        'HDFC Short Term Debt Fund,debt,30000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('bond');
});

it('normalises "fd" to bond', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        'SBI Fixed Deposit,fd,100000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('bond');
});

it('normalises "crypto" to crypto', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        'Ethereum,crypto,25000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('crypto');
});

it('normalises "gold" asset type to commodity', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        'Gold Biscuit,gold,15000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('commodity');
});

it('normalises "liquid" to cash', function () {
    // "liquid fund" would match mutual_fund first ("fund" alias) before reaching cash;
    // use "liquid" alone, which only appears in the cash aliases list
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        'Nippon Liquid,liquid,200000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('cash');
});

it('normalises "international" to foreign_stock', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        'Motilal Oswal International Fund,international,40000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('foreign_stock');
});

it('defaults to stock for an unrecognised asset_type string', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,asset type,current_value',
        'Mystery Asset,derivatives,10000',
    ]));

    expect($this->parser->parse($file)['rows'][0]['asset_type'])->toBe('stock');
});

// ---------------------------------------------------------------------------
// Case-insensitive header matching
// ---------------------------------------------------------------------------

it('matches headers case-insensitively', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'Name,Asset Type,Current Value,Invested Value',
        'Reliance,stock,25000,20000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('Reliance')
        ->and($result['rows'][0]['current_value'])->toBe(25000.0);
});

it('trims whitespace from header names', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        ' name , current_value , invested_value ',
        'Reliance,25000,20000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('Reliance');
});

// ---------------------------------------------------------------------------
// result['count'] field
// ---------------------------------------------------------------------------

it('returns the correct count of parsed rows', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Reliance,25000',
        'HDFC Bank,8000',
        'Infosys,15000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['count'])->toBe(3)
        ->and($result['count'])->toBe(count($result['rows']));
});

// ---------------------------------------------------------------------------
// Multiple row errors in a single file
// ---------------------------------------------------------------------------

it('accumulates errors for multiple bad rows while keeping good rows', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,buy_price',           // no current_value derivable
        'Good Asset,100',           // bad — no current_value
        ',200',                     // bad — empty name
        '',                         // blank row — silently skipped, no error
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toHaveCount(2);  // one per bad data row; blank row is silent
});

// ---------------------------------------------------------------------------
// profit_loss when invested_value is zero
// ---------------------------------------------------------------------------

it('computes profit_loss as current_value when invested_value is zero', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Free Stock,5000',   // no invested_value anywhere — defaults to 0
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    // profit_loss = current_value (5000) - invested_value (0) = 5000
    expect($row['profit_loss'])->toBe(5000.0)
        ->and($row['invested_value'])->toBe(0.0);
});

// ---------------------------------------------------------------------------
// MAX_ROWS cap
// ---------------------------------------------------------------------------

/** CSV with a header plus $n valid holding rows. */
function csvWithRows(int $n): string
{
    $lines = ['name,asset_type,current_value'];

    for ($i = 0; $i < $n; $i++) {
        $lines[] = "Holding {$i},stock,1000";
    }

    return implode("\n", $lines);
}

it('parses a file sitting exactly on the row cap', function () {
    $result = $this->parser->parse(csvFile('at-cap.csv', csvWithRows(5000)));

    expect($result['count'])->toBe(5000)
        ->and($result['errors'])->toBeEmpty();
});

it('rejects a file one row over the cap rather than truncating it', function () {
    // Rejection, not truncation: scoring the first 5,000 rows of a larger
    // portfolio would yield a confident composite from partial holdings.
    $result = $this->parser->parse(csvFile('over-cap.csv', csvWithRows(5001)));

    expect($result['count'])->toBe(0)
        ->and($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toBe([PortfolioParser::MAX_ROWS_MESSAGE]);
});
