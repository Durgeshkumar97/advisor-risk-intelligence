<?php

namespace App\Services\Amfi;

/**
 * SchemeNameNormalizer
 *
 * Reduces a mutual-fund scheme name to the words that identify the scheme,
 * so the name on a broker export and the name in AMFI's NAVAll.txt compare
 * equal when they mean the same scheme:
 *
 *   "HDFC Banking & PSU Debt Fund - Direct Plan - Growth Option"
 *   "HDFC Banking and PSU Debt Fund"                     → "hdfc banking and psu"
 *
 * Applied to both sides — AMFI rows at amfi:sync, holdings at match time —
 * so every rule here only has to be consistent, not linguistically perfect.
 *
 * What it drops is what never tells two schemes apart: plan (direct/regular),
 * option (growth/IDCW and its frequencies), and filler ("fund", "scheme").
 * What it rewrites is what differs only in spelling: "&" vs "and", "Mid-Cap"
 * vs "Midcap", an AMC's short name vs its full one, and SEBI's 2026 debt
 * category renames ("Short Duration" → "Short Term"), which AMCs carried
 * into their scheme names while advisors' files still show the old ones.
 */
class SchemeNameNormalizer
{
    /*
    | Phrases rewritten before words are dropped, longest first so that
    | "ultra short duration" is not half-consumed by "short duration".
    |
    | Debt renames: SEBI circular HO/24/13/15(2)2026-IMD-RAC4/I/5764/2026
    | (26 Feb 2026) renamed the duration categories. NAVAll.txt shows AMCs
    | renaming schemes to match, e.g. "Sundaram Ultra Short to Short Term Fund
    | (Formerly Known as Sundaram Low Duration Fund)".
    */
    private const PHRASES = [
        'income distribution cum capital withdrawal' => ' ',
        'ultra short duration' => 'ultra short term',
        'medium to long duration' => 'medium to long term',
        'low duration' => 'ultra short to short term',
        'short duration' => 'short term',
        'medium duration' => 'medium term',
        'long duration' => 'long term',
        'dynamic bond' => 'dynamic term',
        'floating rate' => 'floating interest rates',
        'floater' => 'floating interest rates',
        'banking and psu debt' => 'banking and psu',
        'fund of funds' => 'fof',
        'fund of fund' => 'fof',
        'dividend yield' => 'dividendyield',
        'tax saver' => 'taxsaver',
        'g sec' => 'gsec',
    ];

    /* Two-word segment names joined, so "mid cap", "mid-cap" and "midcap" agree. */
    private const JOINED = ['large', 'mid', 'small', 'micro', 'flexi', 'multi'];

    /* AMC short names a broker export uses, expanded to AMFI's spelling. */
    private const AMC_ALIASES = [
        'icici pru' => 'icici prudential',
        'absl' => 'aditya birla sun life',
        'aditya birla sl' => 'aditya birla sun life',
        'reliance' => 'nippon india',
        'ppfas' => 'parag parikh',
        'mirae' => 'mirae asset',
        'motilal' => 'motilal oswal',
        'franklin' => 'franklin india',
    ];

    /* Words that name a plan, an option or nothing at all. */
    private const DROP = [
        'direct', 'regular', 'growth', 'idcw', 'dividend', 'div', 'payout',
        'reinvestment', 'reinvest', 'reinv', 're', 'investment', 'plan', 'option', 'opt',
        'fund', 'funds', 'scheme', 'bonus', 'cumulative',
        'daily', 'weekly', 'fortnightly', 'monthly', 'quarterly', 'half', 'yearly', 'annual',
        'formerly', 'known', 'as', 'erstwhile', 'the', 'mf',
    ];

    public function normalize(string $name): string
    {
        $text = mb_strtolower($name);

        // "(Formerly Known as X)" / "(erstwhile X)": the old name is not this scheme's name.
        // "(No. of segregated portfolios- 3)": AMFI's note on a scheme that has
        // side-pocketed a default, not part of the name an advisor's file uses.
        $text = preg_replace('/\((?:formerly|erstwhile|no\.? of segregated)[^)]*\)?/u', ' ', $text);

        $text = str_replace(['&', '’', '‘', '`'], [' and ', "'", "'", "'"], $text);
        $text = str_replace("'", '', $text);                 // children's → childrens
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        $text = ' '.trim(preg_replace('/\s+/', ' ', $text)).' ';

        $joined = implode('|', self::JOINED);
        $text = preg_replace('/ ('.$joined.') caps? /', ' $1cap ', $text);
        $text = preg_replace('/ ('.$joined.') caps? /', ' $1cap ', $text);   // "large mid cap" overlaps

        foreach (self::AMC_ALIASES as $short => $full) {
            if (str_starts_with($text, ' '.$short.' ') && ! str_starts_with($text, ' '.$full.' ')) {
                $text = ' '.$full.substr($text, strlen($short) + 1);
            }
        }

        foreach (self::PHRASES as $from => $to) {
            $text = str_replace(' '.$from.' ', ' '.$to.' ', $text);
        }

        $words = array_filter(
            explode(' ', trim($text)),
            fn (string $word) => $word !== '' && ! in_array($word, self::DROP, true),
        );

        // "Payout & Re-investment of …" leaves its joining words behind.
        while (in_array(end($words), ['and', 'of', 'or'], true)) {
            array_pop($words);
        }

        return implode(' ', $words);
    }

    /**
     * The normalised former name a scheme carries in brackets — "ICICI
     * Prudential Large Cap Fund (erstwhile Bluechip Fund)" → "icici prudential
     * bluechip" — or null. A bare former name ("Bluechip Fund") is read as the
     * same AMC's: the AMC words are taken from the current name.
     */
    public function formerName(string $name): ?string
    {
        if (! preg_match('/\((?:formerly\s+known\s+as|formerly|erstwhile)\s+([^)]+)\)?/iu', $name, $m)) {
            return null;
        }

        $former = $this->normalize($m[1]);
        $current = $this->normalize($name);

        if ($former === '' || $former === $current) {
            return null;
        }

        // Same first word = the former name already names its AMC.
        $firstOf = fn (string $s) => explode(' ', $s)[0];
        if ($firstOf($former) === $firstOf($current)) {
            return $former;
        }

        $amc = $this->amcPrefix($current, $name);

        return $amc === '' ? $former : $amc.' '.$former;
    }

    /** Words of $normalized that precede the first word the bracket-free name shares with a category. */
    private function amcPrefix(string $normalized, string $name): string
    {
        $bare = $this->normalize(preg_replace('/\([^)]*\)?/u', ' ', $name));
        $known = ['aditya birla sun life', 'icici prudential', 'nippon india', 'mirae asset', 'motilal oswal',
            'parag parikh', 'franklin india', 'kotak mahindra', 'bank of india', 'canara robeco', 'bajaj finserv',
            'mahindra manulife', 'baroda bnp paribas', 'invesco india', 'quant', 'navi', 'groww', 'zerodha',
            'whiteoak capital', 'helios', 'old bridge', 'union', 'shriram', 'samco', 'iti', 'jm', 'lic', 'hsbc',
            'pgim india', 'sundaram', 'tata', 'uti', 'sbi', 'hdfc', 'axis', 'dsp', 'edelweiss', 'bandhan', 'kotak',
            '360 one', 'trust', 'taurus', 'quantum', 'angel one', 'unifi', 'jio blackrock', 'capitalmind', 'choice'];

        foreach ($known as $amc) {
            if (str_starts_with($bare.' ', $amc.' ')) {
                return $amc;
            }
        }

        return explode(' ', $bare)[0] ?? '';
    }
}
