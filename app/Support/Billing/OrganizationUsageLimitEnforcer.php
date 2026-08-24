<?php

namespace App\Support\Billing;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

/**
 * Single P2-backed authority enforcing quantitative plan limits (P5-004).
 * Callers must already hold a `lockForUpdate()` row lock on the owning
 * organization within the active transaction and recount current usage
 * under that lock immediately before calling this, so concurrent create
 * requests serialize on the organization row instead of racing an
 * unsynchronized `count < limit` check.
 *
 * Only an explicitly finite launch-plan limit is enforced. A generic
 * pre-subscription trial (no chosen plan), an explicitly unlimited limit,
 * and an undeclared/unavailable limit all impose no ceiling here, mirroring
 * `OrganizationFeatureEntitlement`'s trial and fail-closed semantics: a
 * quantitative limit is never invented for a dimension the launch
 * configuration has not enabled.
 */
final class OrganizationUsageLimitEnforcer
{
    public static function assertCanAdd(
        Organization $lockedOrganization,
        string $limitKey,
        int $currentUsage,
        string $errorField,
        string $errorMessage,
        ?PlanCatalog $catalog = null,
    ): void {
        $access = OrganizationSubscriptionAccessResolver::resolve($lockedOrganization, $catalog);

        if ($access->onTrial && $access->plan === null) {
            return;
        }

        $limit = PlanEntitlementResolver::limit($access->plan, $limitKey, $catalog);

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
