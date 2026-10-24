<?php

namespace App\Services\Amfi;

use App\Exceptions\AmfiSyncFailed;
use Carbon\CarbonImmutable;

/**
 * NavAllParser
 *
 * Turns AMFI's NAVAll.txt into mf_schemes rows. The file is not CSV: scheme
 * rows are ';'-separated, but they are interleaved with category headers
 * ("Open Ended Schemes(Equity Scheme - Large Cap Fund)"), AMC names and blank
 * lines, and each scheme row belongs to the last category and AMC seen.
 *
 * Columns are mapped by header name, not position, because AMFI has changed
 * the layout before (the older one has no Plan/Option columns).
 */
class NavAllParser
{
    public function __construct(private readonly SchemeNameNormalizer $normalizer) {}

    /**
     * @return array{rows: list<array<string, mixed>>, file_date: ?CarbonImmutable}
     */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        if (trim($contents) === '') {
            throw new AmfiSyncFailed('AMFI file is empty.');
        }

        $lines = preg_split('/\r\n|\n/', $contents);
        $header = array_shift($lines);
        $columns = $this->mapHeader($header);
        $fieldCount = count(explode(';', $header));

        $rows = [];
        $dateCounts = [];
        $amc = null;
        $type = null;
        $broad = null;
        $sub = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(Open Ended|Close Ended|Interval Fund) Schemes?\s*\((.*)\)\s*$/u', $line, $m)) {
                $type = match ($m[1]) {
                    'Open Ended' => 'open',
                    'Close Ended' => 'close',
                    default => 'interval',
                };
                [$broad, $sub] = $this->splitCategory($m[2]);

                continue;
            }

            $fields = explode(';', $line);

            if (count($fields) !== $fieldCount) {
                // Not a data row: the line names an AMC.
                if (! str_contains($line, ';')) {
                    $amc = $line;
                }

                continue;
            }

            $code = trim($fields[$columns['code']]);
            if ($code === '') {
                continue;
            }

            $name = trim($fields[$columns['name']]);
            $nav = trim($fields[$columns['nav']]);
            $date = $this->parseDate(trim($fields[$columns['date']]));

            if ($date !== null) {
                $dateCounts[$date] = ($dateCounts[$date] ?? 0) + 1;
            }

            $rows[] = [
                'scheme_code' => $code,
                'isin_growth' => $this->isin($columns['growth'], $fields),
                'isin_reinvest' => $this->isin($columns['reinvest'], $fields),
                'scheme_name' => $name,
                'normalized_name' => $this->normalizer->normalize($name),
                'former_name_normalized' => $this->normalizer->formerName($name),
                'amc' => $amc,
                'scheme_type' => $type ?? 'open',
                'broad_category' => $broad,
                'sub_category' => $sub,
                'latest_nav' => is_numeric($nav) ? (float) $nav : null,
                'nav_date' => $date,
            ];
        }

        return ['rows' => $rows, 'file_date' => $this->modeDate($dateCounts)];
    }

    /** @return array{code: int, name: int, nav: int, date: int, growth: ?int, reinvest: ?int} */
    private function mapHeader(string $header): array
    {
        $map = ['growth' => null, 'reinvest' => null];

        foreach (explode(';', $header) as $i => $title) {
            $title = mb_strtolower(trim($title));

            if ($title === 'scheme code') {
                $map['code'] = $i;
            } elseif ($title === 'scheme name') {
                $map['name'] = $i;
            } elseif ($title === 'net asset value') {
                $map['nav'] = $i;
            } elseif ($title === 'date') {
                $map['date'] = $i;
            } elseif (str_contains($title, 'isin') && str_contains($title, 'reinvest')) {
                $map['reinvest'] = $i;
            } elseif (str_contains($title, 'isin') && (str_contains($title, 'growth') || str_contains($title, 'payout'))) {
                $map['growth'] = $i;
            }
        }

        foreach (['code', 'name', 'nav', 'date'] as $required) {
            if (! isset($map[$required])) {
                throw new AmfiSyncFailed('Unrecognised AMFI file header: '.mb_substr(trim($header), 0, 120));
            }
        }

        return $map;
    }

    /** @return array{0: string, 1: ?string} */
    private function splitCategory(string $inner): array
    {
        $parts = explode(' - ', $inner, 2);
        $broad = trim(preg_replace('/\s*\*+\s*$/', '', trim($parts[0])));
        $sub = isset($parts[1]) ? trim($parts[1]) : null;

        return [$broad, $sub === '' ? null : $sub];
    }

    private function isin(?int $index, array $fields): ?string
    {
        if ($index === null) {
            return null;
        }

        $value = trim($fields[$index]);

        return $value === '' || $value === '-' ? null : $value;
    }

    private function parseDate(string $value): ?string
    {
        if (! preg_match('/^\d{1,2}-[A-Za-z]{3}-\d{4}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!d-M-Y', $value);

        return $date === false ? null : $date->format('Y-m-d');
    }

    /**
     * The date most rows carry. Not the max: liquid and overnight funds are
     * dated a day ahead, and one stray future-dated row must not make a stale
     * file look fresh. A tie goes to the later date.
     *
     * @param  array<string, int>  $counts
     */
    private function modeDate(array $counts): ?CarbonImmutable
    {
        if ($counts === []) {
            return null;
        }

        $best = null;
        foreach ($counts as $date => $count) {
            if ($best === null || $count > $counts[$best] || ($count === $counts[$best] && $date > $best)) {
                $best = (string) $date;
            }
        }

        return CarbonImmutable::parse($best)->startOfDay();
    }
}
