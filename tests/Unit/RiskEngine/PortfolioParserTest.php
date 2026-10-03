<?php

use App\Contracts\FxRateProvider;
use App\Models\PortfolioFile;
use App\Services\RiskEngine\PortfolioParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FixedFxRate;

uses(\Tests\TestCase::class);

beforeEach(function () {
    Storage::fake('portfolios');
    Carbon::setTestNow('2026-10-01 10:00:00');

    // No exchange rate unless a test sets one.
    app()->instance(FxRateProvider::class, FixedFxRate::none());
    $this->parser = new PortfolioParser;
});

/** A parser that converts US dollars at the fixed test rate, dated $asOf. */
function usdParser(string $asOf = '2026-09-29'): PortfolioParser
{
    return new PortfolioParser(fxRates: new FixedFxRate(asOf: $asOf));
}

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
        ->and($result['rows'][0]['current_value'])->toBe(25000.0)
        ->and($result['rows'][0]['invested_value'])->toBe(20000.0)
        ->and($result['rows'][0]['profit_loss'])->toBe(5000.0);
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

it('rejects a file with no market-value column with one file-level error, not per-row errors', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,buy_price',           // no current_value column and no market price to derive one
        'Good Asset,100',
        ',200',
        '',
    ]));

    $result = $this->parser->parse($file);

    // One specific reason is what reaches the advisor; per-row errors would
    // bury it behind the job's generic "No valid holdings found" message.
    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toBe([PortfolioParser::NO_MARKET_VALUE_MESSAGE]);
});

it('accumulates per-row errors for multiple bad rows while keeping good rows', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Good Asset,25000',         // good
        ',200',                     // bad — values but no name
        'Zero Value Asset,0',       // bad — value cannot be resolved
        '',                         // blank row — silently skipped, no error
    ]));

    $result = $this->parser->parse($file);

    expect(array_column($result['rows'], 'name'))->toBe(['Good Asset'])
        ->and($result['errors'])->toBe([
            'Row 3: skipped (missing required data).',
            'Row 4: skipped (missing required data).',
        ]);
});

// ---------------------------------------------------------------------------
// profit_loss when invested_value is zero
// ---------------------------------------------------------------------------

it('keeps cost and P&L unknown when the file has no cost basis', function () {
    $file = csvFile('portfolio.csv', implode("\n", [
        'name,current_value',
        'Free Stock,5000',   // no invested_value anywhere, and no cost price to derive it
    ]));

    $row = $this->parser->parse($file)['rows'][0];

    // Unknown is not zero: a 0 cost would read as +100% profit and hide losses.
    expect($row['invested_value'])->toBeNull()
        ->and($row['profit_loss'])->toBeNull()
        ->and($row['invested_value_source'])->toBe('unknown');
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

// ---------------------------------------------------------------------------
// Unrecognised asset types
// ---------------------------------------------------------------------------

it('warns when an asset type is not recognised, and still scores the holding', function () {
    $file = csvFile('unknown-type.csv', implode("\n", [
        'name,asset_type,current_value',
        'My Flat,real estate,5000000',
        'TCS,stock,150000',
    ]));

    $result = $this->parser->parse($file);

    // The holding is kept and normalised to stock — that part is unchanged.
    expect($result['count'])->toBe(2)
        ->and($result['rows'][0]['asset_type'])->toBe('stock')
        ->and($result['errors'])->toBeEmpty();

    // ...but it is no longer silent about having done so.
    expect($result['warnings'])->toHaveCount(1)
        ->and($result['warnings'][0])->toContain('real estate')
        ->and($result['warnings'][0])->toContain('scored as equity');
});

it('groups repeats of the same unrecognised type into one warning with a count', function () {
    $file = csvFile('repeat-unknown.csv', implode("\n", [
        'name,asset_type,current_value',
        'Flat A,real estate,5000000',
        'Flat B,real estate,3000000',
        'Policy,insurance,200000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['warnings'])->toHaveCount(2)
        ->and($result['warnings'][0])->toContain('2 holdings');
});

it('emits no warnings for a file whose asset types are all recognised', function () {
    $file = csvFile('known-types.csv', implode("\n", [
        'name,asset_type,current_value',
        'TCS,stock,150000',
        'HDFC Fund,mutual fund,90000',
        'Gold,commodity,50000',
    ]));

    expect($this->parser->parse($file)['warnings'])->toBeEmpty();
});

it('resets warnings between parses so one file does not inherit another\'s', function () {
    $dirty = csvFile('dirty.csv', "name,asset_type,current_value\nFlat,real estate,100");
    $clean = csvFile('clean.csv', "name,asset_type,current_value\nTCS,stock,100");

    expect($this->parser->parse($dirty)['warnings'])->toHaveCount(1);
    expect($this->parser->parse($clean)['warnings'])->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Real broker export shapes (synthetic fixtures — see tests/Support/BrokerExportFixtures.php)
// ---------------------------------------------------------------------------

/** Build a fixture on the fake disk and return a PortfolioFile stub for it. */
function fixtureFile(string $filename, callable $build): PortfolioFile
{
    $path = Storage::disk('portfolios')->path($filename);
    @mkdir(dirname($path), 0777, true);
    $build($path);

    return (new PortfolioFile)->forceFill(['path' => $filename]);
}

it('reads a Groww mutual-fund export whose header is on row 10', function () {
    $file = fixtureFile('groww.xlsx', [\Tests\Support\BrokerExportFixtures::class, 'growwMutualFundHoldings']);

    $result = $this->parser->parse($file);

    expect($result['errors'])->toBeEmpty()
        ->and($result['rows'])->toHaveCount(3)
        ->and(array_column($result['rows'], 'name'))->toBe([
            'Example Bluechip Fund Direct Growth',
            'Example Midcap Opportunities Fund Direct Growth',
            'Example Liquid Fund Direct Growth',
        ])
        ->and(array_column($result['rows'], 'quantity'))->toBe([1234.567, 812.345, 12.5])
        ->and(array_column($result['rows'], 'current_value'))->toBe([55000.0, 64000.0, 43500.0])
        ->and(array_column($result['rows'], 'invested_value'))->toBe([50000.0, 60000.0, 40000.0])
        ->and(array_column($result['rows'], 'invested_value_source'))->toBe(['file', 'file', 'file'])
        ->and($result['rows'][0]['isin'])->toBe('INF000TEST01');
});

it('converts an INDmoney US-dollar export to rupees and records the rate and the dollar amounts', function () {
    $file = fixtureFile('indmoney.xls', [\Tests\Support\BrokerExportFixtures::class, 'indmoneyUsStocks']);

    $result = usdParser()->parse($file);

    // 16 = every holding on rows 9–24; the blank rows and the 7-line
    // disclaimer block contributed none.
    expect($result['rows'])->toHaveCount(16)
        ->and($result['errors'])->toBeEmpty();

    // Row 9: 0.25 shares at an average of $10 — $2.50 paid.
    $first = $result['rows'][0];

    expect($first['name'])->toBe('TSTA')
        ->and($first['quantity'])->toBe(0.25)
        ->and($first['invested_value'])->toBe(239.96)        // 2.50 × 95.985
        ->and($first['current_value'])->toBe(239.96)
        ->and($first['buy_price'])->toBe(959.85)             // 10 × 95.985
        ->and($first['currency'])->toBe('USD')
        ->and($first['fx'])->toBe(['rate' => 95.985, 'as_of' => '2026-09-29', 'source' => FixedFxRate::SOURCE])
        ->and($first['original'])->toBe(['current_value' => 2.5, 'invested_value' => 2.5, 'buy_price' => 10.0, 'current_price' => 0.0]);

    // Every row is converted: none is left at its dollar figure.
    foreach ($result['rows'] as $row) {
        expect($row['current_value'])->toBe(round($row['original']['current_value'] * 95.985, 2))
            ->and($row['fx']['rate'])->toBe(95.985);
    }
});

it('values a US-dollar file that has a cost and no market value at cost, with the gain or loss unknown', function () {
    $file = fixtureFile('indmoney.xls', [\Tests\Support\BrokerExportFixtures::class, 'indmoneyUsStocks']);

    $result = usdParser()->parse($file);
    $last = $result['rows'][15];     // row 24: 7.75 shares at an average of $122.50

    expect(array_unique(array_column($result['rows'], 'value_basis')))->toBe(['cost'])
        ->and($last['original']['invested_value'])->toBe(949.38)
        ->and($last['current_value'])->toBe($last['invested_value'])     // carried at what was paid
        ->and($last['invested_value_source'])->toBe('derived')
        ->and($last['profit_loss'])->toBeNull()                          // unknown, not zero
        ->and($last['current_price'])->toBe(0.0)                         // no market price is invented
        ->and($result['warnings'])->toBe([sprintf(PortfolioParser::VALUED_AT_COST_WARNING, 16, 's are', 'they are')]);
});

it('converts a US-dollar file that has market values at market, keeping its gain or loss', function () {
    $file = csvFile('us.csv', implode("\n", [
        'Stock Symbol,Quantity,Avg. Price ($),Market Value ($)',
        'TSTA,2,100,250',
    ]));

    $result = usdParser()->parse($file);
    $row = $result['rows'][0];

    expect($row['value_basis'])->toBe('market')
        ->and($row['current_value'])->toBe(23996.25)       // 250 × 95.985
        ->and($row['invested_value'])->toBe(19197.0)       // 200 × 95.985
        ->and($row['profit_loss'])->toBe(4799.25)
        ->and($row['current_price'])->toBe(11998.13)       // 125 × 95.985
        ->and($row['original']['current_value'])->toBe(250.0)
        ->and($result['warnings'])->toBeEmpty();
});

it('scores a US-dollar holding with no stated type as a foreign stock, without an unknown-type warning', function () {
    $typed = csvFile('typed.csv', "Stock Symbol,Type,Quantity,Avg. Price ($),Market Value ($)\nTSTA,ETF,2,100,250\nTSTB,,2,100,250\n");
    $result = usdParser()->parse($typed);

    expect(array_column($result['rows'], 'asset_type'))->toBe(['etf', 'foreign_stock'])
        ->and($result['warnings'])->toBeEmpty();
});

it('rejects a US-dollar file when no exchange rate is set, rather than reading dollars as rupees', function () {
    $file = fixtureFile('indmoney.xls', [\Tests\Support\BrokerExportFixtures::class, 'indmoneyUsStocks']);

    $result = $this->parser->parse($file);     // no rate configured

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toBe([PortfolioParser::NO_FX_RATE_MESSAGE])
        ->and(PortfolioParser::NO_FX_RATE_MESSAGE)->toBe("US-dollar holdings can't be valued yet: no exchange rate is set.");
});

it('rejects a US-dollar file when the rate is more than 31 days old, naming its date', function () {
    $file = fixtureFile('indmoney.xls', [\Tests\Support\BrokerExportFixtures::class, 'indmoneyUsStocks']);

    $expired = usdParser('2026-08-30')->parse($file);      // 32 days before 1 Oct
    $lastDay = usdParser('2026-08-31')->parse($file);      // 31 days

    expect($expired['rows'])->toBeEmpty()
        ->and($expired['errors'])->toBe(["US-dollar holdings can't be valued: the exchange rate on file is dated 30 Aug 2026, which is more than 31 days old."])
        ->and($lastDay['rows'])->toHaveCount(16);
});

it('accepts a rate more than 7 days old and says so in a warning', function () {
    $file = fixtureFile('indmoney.xls', [\Tests\Support\BrokerExportFixtures::class, 'indmoneyUsStocks']);

    $stale = usdParser('2026-09-23')->parse($file);        // 8 days before 1 Oct
    $fresh = usdParser('2026-09-24')->parse($file);        // 7 days

    $note = 'US-dollar holdings were converted at an exchange rate dated 23 Sep 2026, which is more than 7 days old.';

    expect($stale['rows'])->toHaveCount(16)
        ->and($stale['warnings'])->toContain($note)
        ->and(implode(' ', $fresh['warnings']))->not->toContain('days old');
});

it('still rejects a US-dollar file with neither a market value nor a cost basis', function () {
    $file = csvFile('us-nothing.csv', implode("\n", [
        'Stock Symbol,Quantity,Holding Since ($)',
        'TSTA,2,01-01-2025',
    ]));

    $result = usdParser()->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toBe([PortfolioParser::NO_MARKET_VALUE_MESSAGE]);
});

it('skips a row of a cost-valued US-dollar file whose cost cannot be worked out', function () {
    $file = csvFile('us.csv', implode("\n", [
        'Stock Symbol,Quantity,Avg. Price ($)',
        'TSTA,2,100',
        'TSTB,3,',
    ]));

    $result = usdParser()->parse($file);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['errors'])->toBe(['Row 3: skipped (missing required data).']);
});

it('returns rupee rows exactly as before: no currency, basis or conversion keys', function () {
    $file = fixtureFile('groww.xlsx', [\Tests\Support\BrokerExportFixtures::class, 'growwMutualFundHoldings']);

    $rows = usdParser()->parse($file)['rows'];

    expect(array_keys($rows[0]))->toBe([
        'name', 'asset_type', 'symbol', 'isin', 'quantity', 'buy_price', 'current_price',
        'invested_value', 'current_value', 'profit_loss', 'invested_value_source',
    ])->and($rows[0]['current_value'])->toBe(55000.0);
});

it('identifies the format from content, not the extension', function () {
    $ooxmlNamedXls = fixtureFile('groww-really-xlsx.xls', [\Tests\Support\BrokerExportFixtures::class, 'growwMutualFundHoldings']);
    $biffNamedXlsx = fixtureFile('indmoney-really-xls.xlsx', [\Tests\Support\BrokerExportFixtures::class, 'indmoneyUsStocks']);

    expect($this->parser->parse($ooxmlNamedXls)['rows'])->toHaveCount(3)
        ->and(usdParser()->parse($biffNamedXlsx)['rows'])->toHaveCount(16);
});

it('keeps each value in its own column when a cell in the row is blank (D2-02)', function () {
    $file = fixtureFile('sparse.xlsx', [\Tests\Support\BrokerExportFixtures::class, 'sparseCells']);

    $rows = $this->parser->parse($file)['rows'];

    expect($rows)->toHaveCount(2)
        ->and($rows[1]['name'])->toBe('Example Liquid Fund')
        ->and($rows[1]['isin'])->toBeNull()
        ->and($rows[1]['asset_type'])->toBe('mutual_fund')
        ->and($rows[1]['invested_value'])->toBe(50000.0)
        ->and($rows[1]['current_value'])->toBe(50500.0)
        ->and($rows[1]['quantity'])->toBe(16.2)
        ->and($rows[1]['profit_loss'])->toBe(500.0);
});

it('reads a formula cell with no cached result as blank and never evaluates it', function () {
    $file = fixtureFile('formula.xlsx', [\Tests\Support\BrokerExportFixtures::class, 'formulaWithoutCachedValue']);

    $result = $this->parser->parse($file);

    // Evaluating =B2*100 would have produced a 1000 holding; blank means the
    // row has no value and is reported, not silently computed.
    expect(array_column($result['rows'], 'name'))->toBe(['Plain Holding'])
        ->and($result['errors'])->toBe(['Row 2: skipped (missing required data).']);
});

it('finds a header below a title block in a CSV (row 8)', function () {
    $file = csvFile('titled.csv', implode("\n", [
        'Portfolio statement',
        'Client: Test Investor',
        'Generated: 29-09-2026',
        '', '', '', '',
        'Scheme Name,Units,Current Value',
        'Example Flexi Cap Fund,100,25000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['errors'])->toBeEmpty()
        ->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['name'])->toBe('Example Flexi Cap Fund')
        ->and($result['rows'][0]['current_value'])->toBe(25000.0);
});

it('lets the earliest header win when a later row scores the same', function () {
    $file = csvFile('repeated-header.csv', implode("\n", [
        'name,current_value',
        'Reliance,25000',
        'name,current_value',   // a repeated header row inside the data
        'TCS,30000',
    ]));

    $result = $this->parser->parse($file);

    expect(array_column($result['rows'], 'name'))->toBe(['Reliance', 'TCS'])
        ->and($result['errors'])->toBe(['Row 3: skipped (missing required data).']);
});

it('fails with a message naming the required columns when no header is found', function () {
    $file = csvFile('no-header.csv', implode("\n", [
        'Reliance,25000',
        'TCS,30000',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toBe([PortfolioParser::NO_HEADER_MESSAGE]);
});

it('skips total, disclaimer, footnote, blank and text-only rows but keeps a holding named Total', function () {
    $file = csvFile('junk.csv', implode("\n", [
        'name,current_value',
        'Reliance,25000',
        '',
        'Total Market Index Fund,15000',
        ',',
        'TCS,30000',
        'Total,70000',
        'Grand Total:,70000',
        'Sub Total,70000',
        'Disclaimer: values are indicative,',
        '* Prices as of close,',
        'Holdings are held in demat form,',
    ]));

    $result = $this->parser->parse($file);

    expect(array_column($result['rows'], 'name'))->toBe(['Reliance', 'Total Market Index Fund', 'TCS'])
        ->and($result['errors'])->toBeEmpty();
});

it('strips a UTF-8 byte-order mark from a CSV header (D2-06)', function () {
    $file = csvFile('bom.csv', "\xEF\xBB\xBFName,Current Value\nReliance,25000\n");

    $result = $this->parser->parse($file);

    expect($result['errors'])->toBeEmpty()
        ->and($result['rows'][0]['name'])->toBe('Reliance');
});

it('derives cost only from a cost price, never from a market price', function () {
    $file = csvFile('prices.csv', implode("\n", [
        'name,quantity,avg. price,nav,current_value',
        'With Cost Price,10,90,110,1100',
        'Market Price Only,10,,110,1100',
    ]));

    $rows = $this->parser->parse($file)['rows'];

    expect($rows[0]['invested_value'])->toBe(900.0)
        ->and($rows[0]['invested_value_source'])->toBe('derived')
        ->and($rows[0]['profit_loss'])->toBe(200.0)
        ->and($rows[1]['invested_value'])->toBeNull()
        ->and($rows[1]['invested_value_source'])->toBe('unknown')
        ->and($rows[1]['profit_loss'])->toBeNull();
});

it('rejects a rupee file with no source of current market value, even when it has a cost basis', function () {
    $file = csvFile('cost-only.csv', implode("\n", [
        'name,quantity,avg. price',
        'Example Holding,10,90',
    ]));

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toBe([PortfolioParser::NO_MARKET_VALUE_MESSAGE]);
});

it('names exactly the advertised formats when a file type is not supported', function () {
    $file = (new PortfolioFile)->forceFill(['path' => 'statement.pdf']);

    expect($this->parser->parse($file)['errors'])
        ->toBe(['File type .pdf is not supported. Supported: CSV, XLSX, XLS, or a ZIP of these.']);
});

it('rejects a spreadsheet taller than the read cap instead of silently truncating it', function () {
    // 4,900 holdings, 400 blank rows, then 50 more holdings. Under 5,000
    // holdings in total, but the last 50 sit beyond the rows a reader
    // materialises — reading only the first rows would drop them silently.
    $file = fixtureFile('tall.xlsx', function (string $path) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $rows = [['Name', 'Current Value']];
        foreach (range(1, 4900) as $i) {
            $rows[] = ["Holding {$i}", 1000];
        }
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $spreadsheet->getActiveSheet()->fromArray(
            array_map(fn ($i) => ["Late Holding {$i}", 1000], range(1, 50)),
            null,
            'A5302',
        );
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
    });

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'])->toBe([PortfolioParser::MAX_ROWS_MESSAGE]);
});

// ---------------------------------------------------------------------------
// Funds are recognised before anything else
//
// The type decides the score, and a fund's name routinely contains a word
// that means "stock" somewhere else: "Long Term Equity Fund", "Stock
// Opportunities Fund". The alias map tries stock first, so those were typed
// stock (65) instead of mutual_fund (45). And a scheme name with no "fund" in
// it at all ("Flexi Cap Direct Growth") matched nothing and fell to stock.
// ---------------------------------------------------------------------------

/** The type the parser gives a holding from its name alone (no type column). */
function typeFromName(string $name): string
{
    return test()->parser->parse(csvFile('typed.csv', "name,current_value\n\"{$name}\",1000\n"))['rows'][0]['asset_type'];
}

/** The type the parser gives a value in a Type column. */
function typeFromColumn(string $value): string
{
    return test()->parser->parse(csvFile('typed.csv', "name,type,current_value\nExample Holding,\"{$value}\",1000\n"))['rows'][0]['asset_type'];
}

it('types a fund as a mutual fund even when its name contains a word for stock', function (string $name) {
    expect(typeFromName($name))->toBe('mutual_fund');
})->with([
    'Example Long Term Equity Fund Direct Growth',
    'Example Equity Savings Fund',
    'Example Focused Equity Fund Direct Growth',
    'Example Stock Opportunities Fund',
    'Example Shareholder Yield Fund',
    'Example Frequent Income Fund',           // "eq" inside "frequent"
]);

it('types a scheme as a mutual fund from its scheme words when the name has no "fund" in it', function (string $name) {
    expect(typeFromName($name))->toBe('mutual_fund');
})->with([
    'EXAMPLE ELSS TAX SAVER - DIRECT PLAN',
    'Example Flexi Cap Direct Growth',
    'Example Flexi Cap Regular Growth',
    'Example Midcap Direct Plan IDCW',
    'Example Midcap Regular Plan',
    'Example Small Cap IDCW',
    'Example Liquid Direct Growth',           // was cash, from "liquid"
    'Example Gilt Direct-Growth',             // was bond, from "gilt"
]);

it('does not take "growth" or "direct" on their own as a sign of a fund', function (string $name) {
    expect(typeFromName($name))->toBe('stock');
})->with([
    'Example Growth Industries',
    'Example Direct Line Insurance',
    'Example Flexi Cap Direct',
    'Example Growth Plan Motors',
]);

it('types an exchange traded fund as an ETF, and a fund of funds that holds one as a mutual fund', function () {
    expect(typeFromName('Example Nifty 50 Exchange Traded Fund'))->toBe('etf')     // was mutual_fund, from "fund"
        ->and(typeFromName('Example Nifty 50 ETF'))->toBe('etf')
        ->and(typeFromName('Example Silver ETF Fund of Fund'))->toBe('mutual_fund')
        ->and(typeFromName('Example Gold ETF FoF'))->toBe('mutual_fund');          // was etf
});

it('applies the same rule to a Type column: a fund word wins over "equity"', function () {
    expect(typeFromColumn('Equity Mutual Fund'))->toBe('mutual_fund')
        ->and(typeFromColumn('Equity ETF'))->toBe('etf')
        ->and(typeFromColumn('ELSS'))->toBe('mutual_fund')
        ->and(typeFromColumn('mutual_fund'))->toBe('mutual_fund')
        // No fund word: unchanged.
        ->and(typeFromColumn('Equity'))->toBe('stock')
        ->and(typeFromColumn('Debt'))->toBe('bond');
});

// ---------------------------------------------------------------------------
// Aliases match whole words
//
// The alias map used to match anywhere inside the text, so a short alias
// fired inside an unrelated word: "mf" in "Comfort" made a company a mutual
// fund, "bond" in "Bondada" made one a bond, "etf" in "Netflix" an ETF.
// ---------------------------------------------------------------------------

it('does not find an alias inside a longer word of a company name', function (string $name, string $alias, string $wasTypedAs) {
    // $alias sits inside a word of $name and used to type it as $wasTypedAs.
    expect(typeFromName($name))->toBe('stock');
})->with([
    'mf' => ['Example Comfort Industries', 'mf', 'mutual_fund'],
    'fund' => ['Example Fundamental Research Ltd', 'fund', 'mutual_fund'],
    'etf' => ['Example Netflix Inc', 'etf', 'etf'],
    'bond' => ['Example Bondada Engineering', 'bond', 'bond'],
    'ncd' => ['Example Ncdex Markets', 'ncd', 'bond'],
    'gilt' => ['Example Giltex Ltd', 'gilt', 'bond'],
    'fd' => ['Example Fdc Ltd', 'fd', 'bond'],
    'ppf' => ['Example Ppfas Asset Management', 'ppf', 'bond'],
    'nsc' => ['Example Transcorp Ltd', 'nsc', 'bond'],
    'debt' => ['Example Debtech Ltd', 'debt', 'bond'],
    'gold' => ['Example Goldman Industries', 'gold', 'commodity'],
    'gold, at the end of a word' => ['Example Marigold Exports', 'gold', 'commodity'],
    'silver' => ['Example Silverline Technologies', 'silver', 'commodity'],
    'crypto' => ['Example Cryptography Systems', 'crypto', 'crypto'],
    'cash' => ['Example Cashew Exports', 'cash', 'cash'],
    'liquid' => ['Example Liquidators Ltd', 'liquid', 'cash'],
]);

it('still finds an alias that stands as a word of its own', function (string $name, string $type) {
    expect(typeFromName($name))->toBe($type);
})->with([
    ['Example Gold Mines Ltd', 'commodity'],
    ['Example Silver Bars', 'commodity'],
    ['Example Bitcoin Trust', 'crypto'],
    ['Example FD Plus', 'bond'],
    ['Example Gilt Edge Ltd', 'bond'],
    ['Example 8.5% NCD 2030', 'bond'],
    ['Example G-Sec 2033', 'bond'],
    ['Example T-Bill 91 Day', 'bond'],
    ['Example International Ltd', 'foreign_stock'],
]);

it('still types a fund as a fund when its name carries a short alias as a word', function (string $name) {
    expect(typeFromName($name))->toBe('mutual_fund');
})->with([
    'Example Gold Fund', 'Example Bond Fund', 'Example Debt Fund', 'Example Gilt Fund', 'Example Liquid Fund',
    'Example Cash Management Fund', 'Example FD Plus Fund', 'Example Comfort Savings Fund', 'Example Crypto Fund',
]);

it('matches short aliases and plurals in a Type column as whole words', function (string $value, string $type) {
    expect(typeFromColumn($value))->toBe($type);
})->with([
    ['EQ', 'stock'], ['MF', 'mutual_fund'], ['FD', 'bond'], ['NCD', 'bond'], ['ETF', 'etf'],
    ['Stocks', 'stock'], ['Shares', 'stock'], ['Equities', 'stock'], ['Mutual Funds', 'mutual_fund'], ['ETFs', 'etf'],
    ['Bonds', 'bond'], ['Debentures', 'bond'], ['NCDs', 'bond'], ['Commodities', 'commodity'],
    ['Gold', 'commodity'], ['Liquid', 'cash'], ['Crypto', 'crypto'], ['International', 'foreign_stock'],
]);

it('does not warn about a plural it now recognises', function () {
    $result = $this->parser->parse(csvFile('typed.csv', "name,type,current_value\nExample Holding,Commodities,1000\n"));

    expect($result['rows'][0]['asset_type'])->toBe('commodity')      // was stock, with an "unrecognised" warning
        ->and($result['warnings'])->toBeEmpty();
});
