<?php

namespace App\Actions\Inventory;

use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveUnitOfMeasure
{
    /**
     * Create or update a tenant-scoped UOM while preserving references.
     *
     * @param  array{name: string, symbol: string, active: bool}  $attributes
     */
    public function handle(
        Organization $organization,
        array $attributes,
        ?UnitOfMeasure $unitOfMeasure = null,
    ): UnitOfMeasure {
        return DB::transaction(function () use (
            $organization,
            $attributes,
            $unitOfMeasure,
        ): UnitOfMeasure {
            if ($unitOfMeasure === null) {
                return $organization->unitsOfMeasure()->create($attributes);
            }

            $lockedUnit = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($unitOfMeasure->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUnit->active && ! $attributes['active']) {
                $isReferenced = $lockedUnit
                    ->baseInventoryItems()
                    ->exists()
                    || $lockedUnit
                        ->inventoryItemUnits()
                        ->exists();

                if ($isReferenced) {
                    throw ValidationException::withMessages([
                        'active' => __(
                            'This unit cannot be deactivated while it is assigned to an inventory item or conversion.',
                        ),
                    ]);
                }
            }

            $lockedUnit->update($attributes);

            return $lockedUnit;
        });
    }
}
