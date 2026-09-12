<?php

namespace App\Support\Billing;

use App\Models\Organization;

/**
 * Resolve paid feature entitlement from server-authoritative commercial state.
 */
final class OrganizationFeatureEntitlement
{
    /**
     * Resolve one feature for an organization.
     */
    public static function isGranted(
        Organization $organization,
        string $feature,
        ?PlanCatalog $catalog = null,
    ): bool {
        return self::isGrantedForAccess(
            OrganizationSubscriptionAccessResolver::resolve(
                $organization,
                $catalog,
            ),
            $feature,
            $catalog,
        );
    }

    /**
     * Resolve one feature from an already-resolved commercial state.
     */
    public static function isGrantedForAccess(
        OrganizationSubscriptionAccess $access,
        string $feature,
        ?PlanCatalog $catalog = null,
    ): bool {
        if ($access->onTrial && $access->plan === null) {
            return true;
        }

        return PlanEntitlementResolver::hasFeature(
            $access->plan,
            $feature,
            $catalog,
            $access->planVersionId,
        );
    }
}
