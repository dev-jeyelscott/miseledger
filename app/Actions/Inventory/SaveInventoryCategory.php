<?php

namespace App\Actions\Inventory;

use App\Models\InventoryCategory;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final class SaveInventoryCategory
{
    /**
     * @param  array{name: string, active: bool}  $attributes
     */
    public function handle(
        Organization $organization,
        array $attributes,
        ?InventoryCategory $inventoryCategory = null,
    ): InventoryCategory {
        return DB::transaction(function () use (
            $organization,
            $attributes,
            $inventoryCategory,
        ): InventoryCategory {
            if ($inventoryCategory === null) {
                return $organization->inventoryCategories()->create($attributes);
            }

            $lockedCategory = InventoryCategory::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryCategory->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCategory->update($attributes);

            return $lockedCategory;
        });
    }
}
