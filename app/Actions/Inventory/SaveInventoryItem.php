<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryItemType;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Support\Billing\OrganizationUsageLimitEnforcer;
use App\Support\Billing\UsageLimitKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveInventoryItem
{
    public function __construct(
        private readonly EnsureStockTransferDependencyCanBeDeactivated $ensureStockTransferDependencyCanBeDeactivated,
    ) {}

    /**
     * Create or update an inventory item against locked tenant-owned relations.
     *
     * @param  array{
     *     name: string,
     *     sku: string,
     *     base_unit_of_measure_id: int,
     *     inventory_category_id: int|null,
     *     type: InventoryItemType,
     *     yield_percentage: string,
     *     active: bool
     * }  $attributes
     */
    public function handle(
        Organization $organization,
        array $attributes,
        ?InventoryItem $inventoryItem = null,
    ): InventoryItem {
        return DB::transaction(function () use (
            $organization,
            $attributes,
            $inventoryItem,
        ): InventoryItem {
            if (
                $inventoryItem !== null
                && ! $attributes['active']
            ) {
                $this
                    ->ensureStockTransferDependencyCanBeDeactivated
                    ->assertInventoryItemCanBeDeactivated(
                        $organization,
                        $inventoryItem,
                    );
            }

            $baseUnit = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($attributes['base_unit_of_measure_id'])
                ->lockForUpdate()
                ->first();

            if ($baseUnit === null || ! $baseUnit->active) {
                throw ValidationException::withMessages([
                    'base_unit_of_measure_id' => __(
                        'Select an active unit from the current organization.',
                    ),
                ]);
            }

            $attributes['base_unit_of_measure_id'] = $baseUnit->id;

            $lockedItem = $inventoryItem === null
                ? null
                : InventoryItem::query()
                    ->where('organization_id', $organization->getKey())
                    ->whereKey($inventoryItem->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

            if ($attributes['inventory_category_id'] !== null) {
                $category = InventoryCategory::query()
                    ->where('organization_id', $organization->getKey())
                    ->whereKey($attributes['inventory_category_id'])
                    ->lockForUpdate()
                    ->first();

                $retainsCurrentCategory = $lockedItem !== null
                    && $lockedItem->inventory_category_id === $category?->id;

                if (
                    $category === null
                    || (! $category->active && ! $retainsCurrentCategory)
                ) {
                    throw ValidationException::withMessages([
                        'inventory_category_id' => __(
                            'Select an active category from the current organization.',
                        ),
                    ]);
                }

                $attributes['inventory_category_id'] = $category->id;
            }

            if ($lockedItem === null) {
                $lockedOrganization = Organization::query()
                    ->whereKey($organization->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                OrganizationUsageLimitEnforcer::assertCanAdd(
                    lockedOrganization: $lockedOrganization,
                    limitKey: UsageLimitKey::InventoryItems,
                    currentUsage: $lockedOrganization->inventoryItems()->count(),
                    errorField: 'name',
                    errorMessage: __('This organization has reached its inventory item limit for the current plan.'),
                );

                return $lockedOrganization->inventoryItems()->create($attributes);
            }

            $baseUnitChanged = (
                $lockedItem->base_unit_of_measure_id !== $baseUnit->id
            );

            if (
                $baseUnitChanged
                && $lockedItem->unitConversions()->exists()
            ) {
                throw ValidationException::withMessages([
                    'base_unit_of_measure_id' => __(
                        'The base unit cannot be changed after alternate units have been configured.',
                    ),
                ]);
            }

            if (
                $baseUnitChanged
                && $lockedItem->stockMovements()->exists()
            ) {
                throw ValidationException::withMessages([
                    'base_unit_of_measure_id' => __(
                        'The base unit cannot be changed after stock movements have been recorded.',
                    ),
                ]);
            }

            $lockedItem->update($attributes);

            return $lockedItem;
        });
    }
}
