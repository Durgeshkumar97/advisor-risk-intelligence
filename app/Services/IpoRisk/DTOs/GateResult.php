<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\DTOs;

use InvalidArgumentException;

/**
 * A fired hard gate and the floor it imposes.
 *
 * A gate is an observation severe enough that no combination of good factor
 * scores should be able to average it away — going concern, a regulator
 * action, two years of negative operating cash flow. It clamps the final
 * score up to its floor and never enters the weighted mean.
 *
 * Provenance is not optional here. A gate is the most consequential thing
 * this module can say, so it cannot be said without a citation.
 */
final readonly class GateResult
{
    public function __construct(
        public string $code,
        public int $floor,
        public string $evidence,
        public Provenance $provenance,
    ) {
        if (trim($code) === '') {
            throw new InvalidArgumentException('GateResult requires a non-empty code.');
        }

        if ($floor < 0 || $floor > 100) {
            throw new InvalidArgumentException("GateResult {$code} floor must be 0-100, got {$floor}.");
        }

        if (trim($evidence) === '') {
            throw new InvalidArgumentException("GateResult {$code} requires non-empty evidence.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORY — floor comes from the ruleset, not the caller
    |--------------------------------------------------------------------------
    */

    public static function forCode(string $code, string $evidence, Provenance $provenance): self
    {
        $floor = config("ipo_risk.gate_floors.{$code}");

        if ($floor === null) {
            throw new InvalidArgumentException("Unknown gate code {$code} — no floor in ipo_risk.gate_floors.");
        }

        return new self(
            code: $code,
            floor: (int) $floor,
            evidence: $evidence,
            provenance: $provenance,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'floor' => $this->floor,
            'evidence' => $this->evidence,
            'provenance' => $this->provenance->toArray(),
        ];
    }
}
