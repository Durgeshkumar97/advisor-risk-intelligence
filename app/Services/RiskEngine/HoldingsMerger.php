<?php

namespace App\Services\RiskEngine;

/**
 * HoldingsMerger
 *
 * Combines normalised holdings from several source files of ONE client into a
 * single list, so the client gets one portfolio, one score and one report.
 *
 * This class knows nothing about file formats, brokers or column names. It
 * works only on the normalised holding shape below, which any reader/adapter
 * can produce.
 *
 * NORMALISED HOLDING (input)
 * ──────────────────────────
 *   name, asset_type, symbol, isin, quantity, buy_price, current_price,
 *   invested_value (float|null), current_value, profit_loss (float|null),
 *   invested_value_source ('file'|'derived'|'unknown'),
 *   plus provenance:
 *     source_file  string       the file this holding came from
 *     currency     string       e.g. 'INR'
 *     cost_known   bool         invested_value is a real figure
 *     value_basis  string       'market' (current_value is a market value)
 *     as_of        string|null  date of the figures (Y-m-d), null if unknown
 *
 * MERGE RULES
 * ───────────
 *   - Two holdings match when they have the same currency and value_basis and
 *     either both carry an ISIN and the ISINs are equal, or (when at least one
 *     has no ISIN) their names are equal after trimming, case-folding and
 *     collapsing whitespace.
 *   - Holdings from the SAME source file are never merged with each other, so
 *     a file gives the same result alone as inside a client folder.
 *   - Matched holdings are summed: quantity, current_value, invested_value.
 *   - Unknown cost wins: if any contributing holding has cost_known = false,
 *     the merged invested_value and profit_loss are null. A partial sum would
 *     understate cost and overstate profit.
 *   - as_of is the OLDEST known contributing date, so a stale source cannot
 *     hide behind a fresh one.
 *
 * OUTPUT
 * ──────
 *   The same shape, with `source_file` replaced by `sources`: one entry per
 *   contributing holding — ['source_file', 'cost_known', 'as_of'].
 */
class HoldingsMerger
{
    /**
     * @param  list<array<string, mixed>>  $holdings
     * @return list<array<string, mixed>>
     */
    public function merge(array $holdings): array
    {
        $merged = [];

        foreach ($holdings as $holding) {
            $index = $this->findMatch($merged, $holding);

            if ($index === null) {
                $merged[] = $this->start($holding);
            } else {
                $merged[$index] = $this->combine($merged[$index], $holding);
            }
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $merged
     */
    private function findMatch(array $merged, array $holding): ?int
    {
        foreach ($merged as $index => $candidate) {
            if ($candidate['currency'] !== $holding['currency']
                || $candidate['value_basis'] !== $holding['value_basis']
                || in_array($holding['source_file'], array_column($candidate['sources'], 'source_file'), true)
            ) {
                continue;
            }

            $bothHaveIsin = ! empty($candidate['isin']) && ! empty($holding['isin']);

            $same = $bothHaveIsin
                ? strtoupper(trim($candidate['isin'])) === strtoupper(trim($holding['isin']))
                : $this->normaliseName($candidate['name']) === $this->normaliseName($holding['name']);

            if ($same) {
                return $index;
            }
        }

        return null;
    }

    private function start(array $holding): array
    {
        $source = $this->sourceEntry($holding);
        unset($holding['source_file']);

        return $holding + ['sources' => [$source]];
    }

    private function combine(array $merged, array $holding): array
    {
        $costKnown = $merged['cost_known'] && $holding['cost_known'];

        $quantity = (float) $merged['quantity'] + (float) $holding['quantity'];
        $currentValue = round((float) $merged['current_value'] + (float) $holding['current_value'], 2);
        $investedValue = $costKnown
            ? round((float) $merged['invested_value'] + (float) $holding['invested_value'], 2)
            : null;

        $merged['quantity'] = $quantity;
        $merged['current_value'] = $currentValue;
        $merged['invested_value'] = $investedValue;
        $merged['profit_loss'] = $costKnown ? round($currentValue - $investedValue, 2) : null;
        $merged['cost_known'] = $costKnown;
        $merged['invested_value_source'] = $this->combinedCostSource($merged, $holding, $costKnown);
        $merged['current_price'] = $quantity > 0 ? round($currentValue / $quantity, 2) : 0.0;
        $merged['buy_price'] = ($costKnown && $quantity > 0) ? round($investedValue / $quantity, 2) : 0.0;
        $merged['isin'] = $merged['isin'] ?: ($holding['isin'] ?? null);
        $merged['symbol'] = $merged['symbol'] ?: ($holding['symbol'] ?? null);
        $merged['as_of'] = $this->oldest($merged['as_of'] ?? null, $holding['as_of'] ?? null);
        $merged['sources'][] = $this->sourceEntry($holding);

        return $merged;
    }

    private function combinedCostSource(array $merged, array $holding, bool $costKnown): string
    {
        if (! $costKnown) {
            return 'unknown';
        }

        return in_array('derived', [$merged['invested_value_source'], $holding['invested_value_source']], true)
            ? 'derived'
            : 'file';
    }

    private function sourceEntry(array $holding): array
    {
        return [
            'source_file' => $holding['source_file'],
            'cost_known' => (bool) $holding['cost_known'],
            'as_of' => $holding['as_of'] ?? null,
        ];
    }

    /** The older of two Y-m-d dates; a null (unknown) date never displaces a known one. */
    private function oldest(?string $a, ?string $b): ?string
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return min($a, $b);
    }

    private function normaliseName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }
}
