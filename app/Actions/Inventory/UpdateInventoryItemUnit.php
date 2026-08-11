<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateInventoryItemUnit
{
    /**
     * Update a conversion factor without changing its UOM identity.
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        InventoryItemUnit $inventoryItemUnit,
        string $quantityInBaseUnit,
        bool $active,
    ): InventoryItemUnit {
        return DB::transaction(function () use (
            $organization,
            $inventoryItem,
            $inventoryItemUnit,
            $quantityInBaseUnit,
            $active,
        ): InventoryItemUnit {
            $currentConversion = InventoryItemUnit::query()
                ->where('inventory_item_id', $inventoryItem->getKey())
                ->whereKey($inventoryItemUnit->getKey())
                ->firstOrFail();

            $unitOfMeasure = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($currentConversion->unit_of_measure_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedItem = InventoryItem::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedConversion = InventoryItemUnit::query()
                ->where('inventory_item_id', $lockedItem->getKey())
                ->whereKey($currentConversion->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $unitOfMeasure->active && $active) {
                throw ValidationException::withMessages([
                    'active' => __(
                        'An inactive unit cannot be enabled for inventory use.',
                    ),
                ]);
            }

            if (
                $lockedItem->base_unit_of_measure_id
                === $unitOfMeasure->id
            ) {
                throw ValidationException::withMessages([
                    'quantity_in_base_unit' => __(
                        'The base unit cannot also be configured as an alternate unit.',
                    ),
                ]);
            }

            $lockedConversion->update([
                'quantity_in_base_unit' => $quantityInBaseUnit,
                'active' => $active,
            ]);

            return $lockedConversion;
        });
    }
}
