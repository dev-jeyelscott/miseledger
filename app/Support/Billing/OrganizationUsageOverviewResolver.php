<?php

namespace App\Support\Billing;

use App\Models\Organization;

/**
 * Read-only companion to `OrganizationUsageLimitEnforcer` (P5-004) exposing
 * current-usage-versus-limit guidance for display. Mirrors the enforcer's
 * trial/unlimited/undeclared semantics exactly so the guidance shown here
 * never contradicts what the enforcer would actually allow, but never locks
 * rows, never throws, and never blocks a read: it only tells the caller
 * whether the *next* create of that dimension would currently be blocked.
 */
final class OrganizationUsageOverviewResolver
{
    /**
     * @return array<string, OrganizationUsageOverview>
     */
    public static function forOrganization(
        Organization $organization,
        ?OrganizationSubscriptionAccess $access = null,
        ?PlanCatalog $catalog = null,
    ): array {
        $access ??= OrganizationSubscriptionAccessResolver::resolve($organization, $catalog);

        $overview = [];

        foreach (self::currentUsage($organization) as $key => $current) {
            $overview[$key] = self::overviewFor($access, $catalog, $key, $current);
        }

        return $overview;
    }

    /**
     * @return array<string, int>
     */
    private static function currentUsage(Organization $organization): array
    {
        $usageOrganization = Organization::query()
            ->select('id')
            ->whereKey($organization->getKey())
            ->withCount([
                'memberships',
                'locations',
                'inventoryItems',
            ])
            ->sole();

        return [
            UsageLimitKey::Seats => (int) $usageOrganization->getAttribute('memberships_count'),
            UsageLimitKey::Locations => (int) $usageOrganization->getAttribute('locations_count'),
            UsageLimitKey::InventoryItems => (int) $usageOrganization->getAttribute('inventory_items_count'),
        ];
    }

    private static function overviewFor(
        OrganizationSubscriptionAccess $access,
        ?PlanCatalog $catalog,
        string $key,
        int $current,
    ): OrganizationUsageOverview {
        if ($access->onTrial && $access->plan === null) {
            return new OrganizationUsageOverview(
                key: $key,
                current: $current,
                limit: null,
                isUnlimited: true,
                atLimit: false,
            );
        }

        $limit = PlanEntitlementResolver::limit($access->plan, $key, $catalog);

        if (! $limit->isFinite) {
            return new OrganizationUsageOverview(
                key: $key,
                current: $current,
                limit: null,
                isUnlimited: true,
                atLimit: false,
            );
        }

        return new OrganizationUsageOverview(
            key: $key,
            current: $current,
            limit: $limit->value,
            isUnlimited: false,
            atLimit: $current >= $limit->value,
        );
    }
}
