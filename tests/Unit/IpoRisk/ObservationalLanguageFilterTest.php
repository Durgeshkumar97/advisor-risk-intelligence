<?php

declare(strict_types=1);

use App\Services\IpoRisk\Compliance\ObservationalLanguageFilter;
use App\Services\IpoRisk\Compliance\ProhibitedLanguageException;

uses(\Tests\TestCase::class);

function filter(): ObservationalLanguageFilter
{
    return new ObservationalLanguageFilter;
}

// ---------------------------------------------------------------------------
// False-positive corpus — legitimate observational phrasing must survive
// ---------------------------------------------------------------------------

it('passes observational phrasing', function (string $sentence) {
    expect(filter()->violations($sentence))->toBe([])
        ->and(filter()->isClean($sentence))->toBeTrue();

    filter()->assertClean($sentence);
})->with([
    'related-party disclosure' => ['Related-party disclosures appear in RHP section 7.'],
    'offer for sale' => ['Offer for sale by selling shareholders is 55% of the issue.'],
    'non-disclosure' => ['Not disclosed in the RHP.'],
    'band statement' => ['Falls in the highest risk band for this factor.'],
    'receivables' => ['Receivables form 98% of total assets.'],
    'lock-in expiry' => ['Anchor lock-in expires 90 days after allotment.'],
    'safeguards' => ['Safeguards described in the RHP were not independently verified.'],
    'applicable lock-ins' => ['Applicable lock-in periods are listed in section 4.'],
]);

it('does not mistake a longer word for a prohibited one', function (string $sentence) {
    expect(filter()->violations($sentence))->toBe([]);
})->with([
    'selling' => ['Promoter selling shareholders are named in section 3.'],
    'safeguards' => ['Safeguards were documented.'],
    'buyer' => ['The anchor buyer list is disclosed.'],
    'subscribed' => ['The issue was subscribed 3.2 times on day two.'],
    'advised' => ['The company was advised by external counsel.'],
    'opportunities' => ['Opportunities and threats are described in the MD&A.'],
    'reviewed' => ['The statements were reviewed by the audit committee.'],
]);

// ---------------------------------------------------------------------------
// True-positive corpus — prescriptive and promotional phrasing must fail
// ---------------------------------------------------------------------------

it('blocks prescriptive and promotional phrasing', function (string $sentence, string $term) {
    expect(filter()->violations($sentence))->toContain($term)
        ->and(filter()->isClean($sentence))->toBeFalse();
})->with([
    'avoid' => ['Investors should avoid this issue.', 'avoid'],
    'good investment' => ['This looks like a good investment.', 'good investment'],
    'we suggest' => ['We suggest waiting for listing.', 'we suggest'],
    'will rise' => ['The stock will rise after listing.', 'will rise'],
    'attractive' => ['Valuation appears attractive.', 'attractive'],
    'consider' => ['Consider reviewing the allocation.', 'consider'],
    'discuss' => ['Discuss this with the client.', 'discuss'],
]);

it('throws on prescriptive phrasing, rather than sanitising it', function () {
    filter()->assertClean('Investors should avoid this issue.');
})->throws(ProhibitedLanguageException::class);

it('reports every matched term, not just the first', function () {
    $violations = filter()->violations('We suggest investors should buy — a safe, attractive opportunity.');

    expect($violations)->toContain('we suggest', 'should', 'buy', 'safe', 'attractive', 'opportunity')
        ->and(count($violations))->toBeGreaterThanOrEqual(6);
});

it('names every matched term in the exception', function () {
    try {
        filter()->assertClean('This is a good investment and the price will rise.', 'narrative.summary');
    } catch (ProhibitedLanguageException $e) {
        expect($e->terms)->toContain('good investment', 'will rise')
            ->and($e->getMessage())->toContain('good investment')
            ->and($e->getMessage())->toContain('will rise')
            ->and($e->getMessage())->toContain('narrative.summary')
            ->and($e->subject)->toBe('This is a good investment and the price will rise.');

        return;
    }

    $this->fail('Expected ProhibitedLanguageException.');
});

// ---------------------------------------------------------------------------
// Matching mechanics
// ---------------------------------------------------------------------------

it('matches case-insensitively', function () {
    expect(filter()->violations('SUBSCRIBE now'))->toContain('subscribe')
        ->and(filter()->violations('Should'))->toContain('should');
});

it('matches a multi-word term across a line break', function () {
    expect(filter()->violations("This is a good\n  investment."))->toContain('good investment');
});

it('does not match a multi-word term whose words are separated by other words', function () {
    expect(filter()->violations('A good but illiquid investment vehicle was described.'))
        ->not->toContain('good investment');
});

it('treats an empty string as clean', function () {
    expect(filter()->isClean(''))->toBeTrue();
});

it('accepts an explicit term list for testing', function () {
    $custom = new ObservationalLanguageFilter(['banana']);

    expect($custom->violations('A banana.'))->toBe(['banana'])
        ->and($custom->violations('Investors should buy.'))->toBe([]);
});

it('drops blank and duplicate terms from the list', function () {
    $custom = new ObservationalLanguageFilter(['buy', ' buy ', '', '   ']);

    expect($custom->terms())->toBe(['buy']);
});

it('loads the configured list by default', function () {
    expect(filter()->terms())->toBe(config('ipo_risk.prohibited_terms'));
});

// ---------------------------------------------------------------------------
// I10 — no enum case name or value contains a prohibited term
// ---------------------------------------------------------------------------

it('keeps every IpoRisk enum case free of prohibited language', function () {
    $files = glob(app_path('Services/IpoRisk/Enums/*.php'));

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $enum = 'App\\Services\\IpoRisk\\Enums\\'.basename($file, '.php');

        expect(enum_exists($enum))->toBeTrue("{$enum} should be an enum");

        foreach ($enum::cases() as $case) {
            expect(filter()->violations($case->name))
                ->toBe([], "{$enum}::{$case->name} contains prohibited language");

            expect(filter()->violations((string) $case->value))
                ->toBe([], "{$enum}::{$case->name} value contains prohibited language");

            // Labels are user-facing narrative, so they are held to the
            // same bar as any other published string.
            expect(filter()->violations($case->label()))
                ->toBe([], "{$enum}::{$case->name} label contains prohibited language");
        }
    }
});

it('keeps every configured gate code free of prohibited language', function () {
    foreach (array_keys(config('ipo_risk.gate_floors')) as $code) {
        expect(filter()->violations((string) $code))->toBe([], "gate {$code} contains prohibited language");
    }
});
