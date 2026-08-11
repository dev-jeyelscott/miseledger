<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateInventoryItemUnit
{
    /**
     * Add an item-specific UOM conversion using locked tenant records.
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        int $unitOfMeasureId,
        string $quantityInBaseUnit,
        bool $active,
    ): InventoryItemUnit {
        return DB::transaction(function () use (
            $organization,
            $inventoryItem,
            $unitOfMeasureId,
            $quantityInBaseUnit,
            $active,
        ): InventoryItemUnit {
            $unitOfMeasure = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($unitOfMeasureId)
                ->lockForUpdate()
                ->first();

            if ($unitOfMeasure === null || ! $unitOfMeasure->active) {
                throw ValidationException::withMessages([
                    'unit_of_measure_id' => __(
                        'Select an active unit from the current organization.',
                    ),
                ]);
            }

            $lockedItem = InventoryItem::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedItem->base_unit_of_measure_id
                === $unitOfMeasure->id
            ) {
                throw ValidationException::withMessages([
                    'unit_of_measure_id' => __(
                        'The alternate unit must differ from the base unit.',
                    ),
                ]);
            }

            return $lockedItem->unitConversions()->create([
                'unit_of_measure_id' => $unitOfMeasure->id,
                'quantity_in_base_unit' => $quantityInBaseUnit,
                'active' => $active,
            ]);
        });
    }
}
