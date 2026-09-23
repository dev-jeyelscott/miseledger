<?php

namespace App\Support\Onboarding;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single readiness boundary for first-time setup.
 *
 * An organization is operationally ready when it has an active location, an
 * active inventory item, and every active item has an explicitly resolved
 * opening-stock state (a recorded stock movement or an explicit "no opening
 * stock" mark). Location and item existence are always recalculated from
 * current records.
 *
 * `onboarding_completed_at` is set the first time the full check passes. It
 * only retires the opening-stock condition for items created after launch,
 * which normally receive stock through purchasing or receiving instead.
 */
final class OrganizationSetupReadiness
{
    /**
     * Cheap readiness check for request gates and shared page props.
     */
    public static function isReady(Organization $organization): bool
    {
        if ($organization->onboarding_completed_at !== null) {
            return self::hasActiveLocation($organization)
                && self::hasActiveItem($organization);
        }

        return self::resolve($organization)->ready;
    }

    /**
     * Compute the full checklist state and record first completion.
     */
    public static function resolve(Organization $organization): OrganizationSetupStatus
    {
        $organizationId = $organization->getKey();
        $latched = $organization->onboarding_completed_at !== null;

        $activeLocationCount = Location::query()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->count();

        $activeUnitCount = UnitOfMeasure::query()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->count();

        $activeItemCount = InventoryItem::query()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->count();

        $unresolvedOpeningStockCount = $latched
            ? 0
            : self::unresolvedOpeningStockItems($organization)->count();

        $ready = $activeLocationCount > 0
            && $activeItemCount > 0
            && $unresolvedOpeningStockCount === 0;

        $justCompleted = false;

        if ($ready && ! $latched) {
            $completedAt = now();

            $justCompleted = Organization::query()
                ->whereKey($organizationId)
                ->whereNull('onboarding_completed_at')
                ->update(['onboarding_completed_at' => $completedAt]) === 1;

            $organization->forceFill([
                'onboarding_completed_at' => $completedAt,
            ])->syncOriginalAttribute('onboarding_completed_at');
        }

        return new OrganizationSetupStatus(
            activeLocationCount: $activeLocationCount,
            activeUnitCount: $activeUnitCount,
            activeItemCount: $activeItemCount,
            resolvedOpeningStockCount: $activeItemCount - $unresolvedOpeningStockCount,
            unresolvedOpeningStockCount: $unresolvedOpeningStockCount,
            supplierCount: Supplier::query()
                ->where('organization_id', $organizationId)
                ->where('active', true)
                ->count(),
            supplierItemCount: SupplierItem::query()
                ->where('organization_id', $organizationId)
                ->where('active', true)
                ->count(),
            memberCount: $organization->memberships()->count(),
            suppliersSkipped: $organization->onboarding_suppliers_skipped_at !== null,
            teamSkipped: $organization->onboarding_team_skipped_at !== null,
            ready: $ready,
            justCompleted: $justCompleted,
        );
    }

    /**
     * Active items whose opening-stock state has not been explicitly resolved.
     *
     * @return Builder<InventoryItem>
     */
    public static function unresolvedOpeningStockItems(Organization $organization): Builder
    {
        return InventoryItem::query()
            ->where('organization_id', $organization->getKey())
            ->where('active', true)
            ->whereNull('opening_stock_waived_at')
            ->whereDoesntHave('stockMovements');
    }

    private static function hasActiveLocation(Organization $organization): bool
    {
        return Location::query()
            ->where('organization_id', $organization->getKey())
            ->where('active', true)
            ->exists();
    }

    private static function hasActiveItem(Organization $organization): bool
    {
        return InventoryItem::query()
            ->where('organization_id', $organization->getKey())
            ->where('active', true)
            ->exists();
    }
}
