<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryItemType;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveInventoryItem
{
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

            if ($attributes['inventory_category_id'] !== null) {
                $category = InventoryCategory::query()
                    ->where('organization_id', $organization->getKey())
                    ->whereKey($attributes['inventory_category_id'])
                    ->lockForUpdate()
                    ->first();

                if ($category === null || ! $category->active) {
                    throw ValidationException::withMessages([
                        'inventory_category_id' => __(
                            'Select an active category from the current organization.',
                        ),
                    ]);
                }

                $attributes['inventory_category_id'] = $category->id;
            }

            if ($inventoryItem === null) {
                return $organization->inventoryItems()->create($attributes);
            }

            $lockedItem = InventoryItem::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

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

            $lockedItem->update($attributes);

            return $lockedItem;
        });
    }
}
