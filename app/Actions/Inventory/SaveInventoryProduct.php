<?php

namespace App\Actions\Inventory;

use App\Models\InventoryProduct;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final class SaveInventoryProduct
{
    /**
     * @param  array{name: string, active: bool}  $attributes
     */
    public function handle(
        Organization $organization,
        array $attributes,
        ?InventoryProduct $inventoryProduct = null,
    ): InventoryProduct {
        return DB::transaction(function () use (
            $organization,
            $attributes,
            $inventoryProduct,
        ): InventoryProduct {
            if ($inventoryProduct === null) {
                return $organization->inventoryProducts()->create($attributes);
            }

            $lockedProduct = InventoryProduct::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryProduct->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProduct->update($attributes);

            return $lockedProduct;
        });
    }
}
