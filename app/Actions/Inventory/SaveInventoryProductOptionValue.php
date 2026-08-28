<?php

namespace App\Actions\Inventory;

use App\Models\InventoryProductOption;
use App\Models\InventoryProductOptionValue;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveInventoryProductOptionValue
{
    /**
     * Create or update a controlled option value against a tenant-locked
     * option dimension.
     *
     * @param  array{value: string, active: bool}  $attributes
     */
    public function handle(
        Organization $organization,
        InventoryProductOption $inventoryProductOption,
        array $attributes,
        ?InventoryProductOptionValue $inventoryProductOptionValue = null,
    ): InventoryProductOptionValue {
        return DB::transaction(function () use (
            $organization,
            $inventoryProductOption,
            $attributes,
            $inventoryProductOptionValue,
        ): InventoryProductOptionValue {
            $lockedOption = InventoryProductOption::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryProductOption->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedOption === null) {
                throw ValidationException::withMessages([
                    'inventory_product_option_id' => __(
                        'Select an option dimension from the current organization.',
                    ),
                ]);
            }

            if ($inventoryProductOptionValue === null) {
                return $lockedOption->values()->create([
                    ...$attributes,
                    'organization_id' => $organization->getKey(),
                ]);
            }

            $lockedValue = InventoryProductOptionValue::query()
                ->where('organization_id', $organization->getKey())
                ->where(
                    'inventory_product_option_id',
                    $lockedOption->getKey(),
                )
                ->whereKey($inventoryProductOptionValue->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedValue->update($attributes);

            return $lockedValue;
        });
    }
}
