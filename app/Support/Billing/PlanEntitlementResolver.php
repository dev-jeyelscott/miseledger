<?php

namespace App\Support\Billing;

use App\Enums\PlanCode;
use OutOfBoundsException;

/**
 * Single authority for whether a resolved plan grants a feature and what
 * quantitative limit applies to a dimension, built entirely from
 * `PlanCatalog` (P2-001/P2-002). Deliberately narrow: no role
 * authorization, Stripe mutation, inventory mutation, or quota
 * enforcement happens here, only a read-only answer derived from
 * configuration.
 *
 * Missing, unknown, or invalid plan configuration fails closed: a null
 * plan, a plan absent from the catalog, an undeclared feature, or an
 * undeclared limit key never grants paid-only capability.
 */
final class PlanEntitlementResolver
{
    public static function hasFeature(?PlanCode $plan, string $feature, ?PlanCatalog $catalog = null): bool
    {
        $definition = self::definition($plan, $catalog);

        return $definition?->hasFeature($feature) ?? false;
    }

    public static function limit(?PlanCode $plan, string $key, ?PlanCatalog $catalog = null): PlanEntitlementLimit
    {
        $definition = self::definition($plan, $catalog);

        if ($definition === null) {
            return PlanEntitlementLimit::unavailable();
        }

        try {
            $value = $definition->limit($key);
        } catch (OutOfBoundsException) {
            return PlanEntitlementLimit::unavailable();
        }

        return $value === null ? PlanEntitlementLimit::unlimited() : PlanEntitlementLimit::finite($value);
    }

    private static function definition(?PlanCode $plan, ?PlanCatalog $catalog): ?PlanDefinition
    {
        if ($plan === null) {
            return null;
        }

        return ($catalog ?? new PlanCatalog)->get($plan);
    }
}
