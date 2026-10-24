<?php

declare(strict_types=1);

namespace Tests\Unit\Amfi;

use App\Services\Amfi\SchemeNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SchemeNameNormalizerTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function equalPairs(): array
    {
        return [
            'ampersand vs and' => ['HDFC Banking & PSU Debt Fund', 'HDFC Banking and PSU Debt Fund'],
            'banking & psu without "debt"' => ['Kotak Banking & PSU Fund', 'Kotak Banking and PSU Debt Fund'],
            'plan and option suffixes' => ['Axis Midcap Fund - Direct Plan - Growth Option', 'Axis Midcap Fund'],
            'regular idcw' => ['Axis Midcap Fund - Regular Plan - IDCW', 'Axis Midcap Fund'],
            'mid cap spellings' => ['Axis Mid-Cap Fund', 'Axis Mid Cap Fund'],
            'midcap spelling' => ['Axis Midcap Fund', 'Axis Mid Cap Fund'],
            'old SEBI short duration' => ['Axis Short Duration Fund', 'Axis Short Term Fund'],
            'old SEBI low duration' => ['Axis Low Duration Fund', 'Axis Ultra Short to Short Term Fund'],
            'AMC short name' => ['ICICI Pru Large Cap Fund', 'ICICI Prudential Large Cap Fund'],
            'curly apostrophe' => ['Axis Children’s Fund', "Axis Children's Fund"],
            'idcw payout wording' => [
                'Kotak Arbitrage Fund - IDCW - Payout & Re-investment of Income Distribution cum capital withdrawal',
                'kotak arbitrage',
            ],
            'segregated portfolio note' => ['Franklin India Dynamic Accrual Fund (No. of segregated portfolios- 3)', 'Franklin India Dynamic Accrual Fund'],
        ];
    }

    #[DataProvider('equalPairs')]
    public function test_names_that_mean_the_same_scheme_normalise_equal(string $a, string $b): void
    {
        $n = new SchemeNameNormalizer;

        $this->assertSame($n->normalize($b), $n->normalize($a));
    }

    public function test_numbers_are_kept(): void
    {
        $n = new SchemeNameNormalizer;

        $this->assertNotSame($n->normalize('Nifty Midcap 150'), $n->normalize('Nifty Midcap 50'));
    }

    public function test_former_name_is_read_from_brackets(): void
    {
        $n = new SchemeNameNormalizer;

        $this->assertSame(
            $n->normalize('ICICI Prudential Bluechip Fund'),
            $n->formerName('ICICI Prudential Large Cap Fund (erstwhile Bluechip Fund)'),
        );
        $this->assertNull($n->formerName('Axis Midcap Fund'));
    }
}
