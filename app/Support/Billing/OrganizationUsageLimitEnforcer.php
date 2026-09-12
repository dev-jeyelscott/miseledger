<?php

namespace App\Support\Billing;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

/**
 * Enforce quantitative limits from the exact pinned commercial version.
 */
final class OrganizationUsageLimitEnforcer
{
    /**
     * Assert that one additional resource remains inside the effective limit.
     */
    public static function assertCanAdd(
        Organization $lockedOrganization,
        string $limitKey,
        int $currentUsage,
        string $errorField,
        string $errorMessage,
        ?PlanCatalog $catalog = null,
    ): void {
        $access =
            OrganizationSubscriptionAccessResolver::resolve(
                $lockedOrganization,
                $catalog,
            );

        if ($access->onTrial && $access->plan === null) {
            return;
        }

        $limit = PlanEntitlementResolver::limit(
            $access->plan,
            $limitKey,
            $catalog,
            $access->planVersionId,
        );

        if (! $limit->isFinite) {
            return;
        }

        if ($currentUsage >= $limit->value) {
            throw ValidationException::withMessages([
                $errorField => $errorMessage,
            ]);
        }
    }
}
