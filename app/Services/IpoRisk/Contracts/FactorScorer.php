<?php

declare(strict_types=1);

namespace App\Services\IpoRisk\Contracts;

use App\Services\IpoRisk\DTOs\FactorResult;
use App\Services\IpoRisk\DTOs\IpoInputs;
use App\Services\IpoRisk\Enums\FactorFamily;

/**
 * One deterministic factor.
 *
 * Implementations are pure functions of IpoInputs: same facts in, same score
 * out, no clock, no network, no model. That is what makes a published score
 * reproducible from its ruleset hash months later, and it is why no LLM ever
 * implements this interface — an LLM may extract the facts that become
 * IpoInputs, but it does not get to decide what they are worth.
 *
 * A scorer that cannot find its input returns FactorResult::absent() with a
 * reason. It never guesses, and it never returns a middling default.
 */
interface FactorScorer
{
    /**
     * Stable identifier, unique across the registry, used in output and
     * stored alongside results. Codes beginning with GMP are refused at
     * registration.
     */
    public function code(): string;

    public function family(): FactorFamily;

    public function score(IpoInputs $inputs): FactorResult;
}
