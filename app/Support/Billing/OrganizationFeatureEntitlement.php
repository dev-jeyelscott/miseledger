<?php

namespace App\Support\Billing;

use App\Models\Organization;

/**
 * Single P2-backed authority for whether an organization currently has a
 * paid-only feature, combining `PlanEntitlementResolver` (P2) with the
 * organization's resolved commercial access (`OrganizationSubscriptionAccessResolver`).
 * The only exception layered on top of the narrow plan resolver is trial:
 * an organization on its generic pre-subscription trial has not chosen a
 * plan yet, so it is granted every feature (mirroring the `full` access
 * mode already granted to trials elsewhere) rather than being denied
 * everything simply because no plan has been purchased yet. Once the
 * trial ends, or once a subscription resolves to a concrete plan, only
 * that plan's declared `features` grant access; missing or unknown plan
 * configuration fails closed.
 */
final class OrganizationFeatureEntitlement
{
    public static function isGranted(Organization $organization, string $feature, ?PlanCatalog $catalog = null): bool
    {
        return self::isGrantedForAccess(
            OrganizationSubscriptionAccessResolver::resolve($organization, $catalog),
            $feature,
            $catalog,
        );
    }

    public static function isGrantedForAccess(OrganizationSubscriptionAccess $access, string $feature, ?PlanCatalog $catalog = null): bool
    {
        if ($access->onTrial && $access->plan === null) {
            return true;
        }

        return PlanEntitlementResolver::hasFeature($access->plan, $feature, $catalog);
    }
}
