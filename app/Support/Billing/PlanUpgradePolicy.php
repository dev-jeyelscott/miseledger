<?php

namespace App\Support\Billing;

use App\Enums\PlanCode;

/**
 * The single, server-owned source of plan upgrade eligibility. A target
 * plan is a valid upgrade only when its `tier` strictly outranks the
 * current plan's `tier` -- never inferred from catalog array order, price,
 * limits, or display name.
 */
final readonly class PlanUpgradePolicy
{
    public function __construct(private PlanCatalog $planCatalog) {}

    public function isEligibleUpgrade(PlanCode $current, PlanCode $target): bool
    {
        $currentDefinition = $this->planCatalog->get($current);
        $targetDefinition = $this->planCatalog->get($target);

        return $currentDefinition !== null
            && $targetDefinition !== null
            && $targetDefinition->tier() > $currentDefinition->tier();
    }

    /** @return list<PlanCode> Plans strictly higher-tier than $current, in tier order. */
    public function eligibleUpgradesFrom(PlanCode $current): array
    {
        $currentDefinition = $this->planCatalog->get($current);

        if ($currentDefinition === null) {
            return [];
        }

        $definitions = array_filter(
            $this->planCatalog->all(),
            static fn (PlanDefinition $definition): bool => $definition->tier() > $currentDefinition->tier(),
        );

        usort($definitions, static fn (PlanDefinition $a, PlanDefinition $b): int => $a->tier() <=> $b->tier());

        return array_map(static fn (PlanDefinition $definition): PlanCode => $definition->code, $definitions);
    }
}
