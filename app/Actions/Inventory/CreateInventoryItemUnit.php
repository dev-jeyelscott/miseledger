<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateInventoryItemUnit
{
    private const SCALE = 6;

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
            $this->validateFactor($quantityInBaseUnit);

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

    private function validateFactor(string $factor): void
    {
        try {
            $decimal = BigDecimal::of(trim($factor));
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                'quantity_in_base_unit' => __('A valid decimal quantity is required.'),
            ]);
        }

        if ($decimal->compareTo(BigDecimal::zero()) <= 0) {
            throw ValidationException::withMessages([
                'quantity_in_base_unit' => __(
                    'The conversion factor must be greater than zero.',
                ),
            ]);
        }

        if ($decimal->getScale() > self::SCALE) {
            throw ValidationException::withMessages([
                'quantity_in_base_unit' => __(
                    'The conversion factor must have at most 6 decimal places.',
                ),
            ]);
        }
    }
}
