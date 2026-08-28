<?php

namespace App\Actions\Inventory;

use App\Models\InventoryBrand;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final class SaveInventoryBrand
{
    /**
     * @param  array{name: string, active: bool}  $attributes
     */
    public function handle(
        Organization $organization,
        array $attributes,
        ?InventoryBrand $inventoryBrand = null,
    ): InventoryBrand {
        return DB::transaction(function () use (
            $organization,
            $attributes,
            $inventoryBrand,
        ): InventoryBrand {
            if ($inventoryBrand === null) {
                return $organization->inventoryBrands()->create($attributes);
            }

            $lockedBrand = InventoryBrand::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryBrand->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBrand->update($attributes);

            return $lockedBrand;
        });
    }
}
