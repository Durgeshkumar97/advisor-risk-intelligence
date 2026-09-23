<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Contracts;

use App\Services\IpoRisk\DTOs\GateResult;
use App\Services\IpoRisk\DTOs\IpoInputs;

/**
 * A hard gate: an observation severe enough to set a floor under the final
 * score regardless of how the weighted factors came out.
 *
 * Returns null when the gate did not fire — which is the ordinary case, and
 * is not the same as "we could not tell". A gate that cannot establish its
 * condition from the inputs does not fire; absence of evidence never clamps.
 */
interface GateEvaluator
{
    public function evaluate(IpoInputs $inputs): ?GateResult;
}
