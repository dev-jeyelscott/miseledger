<?php

namespace App\Support\Billing;

use App\Enums\PlanCode;
use OutOfBoundsException;

/**
 * Single plan feature and quantitative-limit authority.
 */
final class PlanEntitlementResolver
{
    /**
     * Resolve a feature against either the current or exact pinned version.
     */
    public static function hasFeature(
        ?PlanCode $plan,
        string $feature,
        ?PlanCatalog $catalog = null,
        ?int $planVersionId = null,
    ): bool {
        $definition = self::definition(
            $plan,
            $catalog,
            $planVersionId,
        );

        return $definition?->hasFeature($feature) ?? false;
    }

    /**
     * Resolve a quantitative limit against the exact commercial version.
     */
    public static function limit(
        ?PlanCode $plan,
        string $key,
        ?PlanCatalog $catalog = null,
        ?int $planVersionId = null,
    ): PlanEntitlementLimit {
        $definition = self::definition(
            $plan,
            $catalog,
            $planVersionId,
        );

        if ($definition === null) {
            return PlanEntitlementLimit::unavailable();
        }

        try {
            $value = $definition->limit($key);
        } catch (OutOfBoundsException) {
            return PlanEntitlementLimit::unavailable();
        }

        return $value === null
            ? PlanEntitlementLimit::unlimited()
            : PlanEntitlementLimit::finite($value);
    }

    /**
     * Resolve the exact immutable definition when a version pin exists.
     */
    private static function definition(
        ?PlanCode $plan,
        ?PlanCatalog $catalog,
        ?int $planVersionId,
    ): ?PlanDefinition {
        if ($plan === null) {
            return null;
        }

        $catalog ??= new PlanCatalog;

        $definition = $planVersionId !== null
            ? $catalog->definitionForVersion(
                $planVersionId,
            )
            : $catalog->get($plan);

        return $definition !== null
            && $definition->code->equals($plan)
                ? $definition
                : null;
    }
}
