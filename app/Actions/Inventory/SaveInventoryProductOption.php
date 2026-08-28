<?php

namespace App\Actions\Inventory;

use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveInventoryProductOption
{
    /**
     * Create or update a controlled option dimension against a
     * tenant-locked product family.
     *
     * @param  array{name: string, active: bool}  $attributes
     */
    public function handle(
        Organization $organization,
        InventoryProduct $inventoryProduct,
        array $attributes,
        ?InventoryProductOption $inventoryProductOption = null,
    ): InventoryProductOption {
        return DB::transaction(function () use (
            $organization,
            $inventoryProduct,
            $attributes,
            $inventoryProductOption,
        ): InventoryProductOption {
            $lockedProduct = InventoryProduct::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryProduct->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedProduct === null) {
                throw ValidationException::withMessages([
                    'inventory_product_id' => __(
                        'Select a product family from the current organization.',
                    ),
                ]);
            }

            if ($inventoryProductOption === null) {
                return $lockedProduct->options()->create([
                    ...$attributes,
                    'organization_id' => $organization->getKey(),
                ]);
            }

            $lockedOption = InventoryProductOption::query()
                ->where('organization_id', $organization->getKey())
                ->where('inventory_product_id', $lockedProduct->getKey())
                ->whereKey($inventoryProductOption->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOption->update($attributes);

            return $lockedOption;
        });
    }
}
