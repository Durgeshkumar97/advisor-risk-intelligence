<?php

use App\Models\PortfolioFile;
use App\Services\RiskEngine\PortfolioParser;
use Illuminate\Support\Facades\Storage;

uses(\Tests\TestCase::class);

beforeEach(function () {
    Storage::fake('portfolios');
    $this->parser = new PortfolioParser();
});

// ---------------------------------------------------------------------------
// Helper — write CSV to fake disk and return a PortfolioFile stub
// ---------------------------------------------------------------------------

function csvFile(string $filename, string $content): PortfolioFile
{
    Storage::disk('portfolios')->put($filename, $content);
    return (new PortfolioFile())->forceFill(['path' => $filename]);
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
    $file = (new PortfolioFile())->forceFill(['path' => 'document.pdf']);

    $result = $this->parser->parse($file);

    expect($result['rows'])->toBeEmpty()
        ->and($result['errors'][0])->toContain('.pdf');
});
