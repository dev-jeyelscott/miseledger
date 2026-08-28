<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryProductOptionValue;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncInventoryItemOptionValues
{
    /**
     * Replace a variant item's controlled option value associations with a
     * tenant- and product-family-contained set of values.
     *
     * @param  list<int>  $inventoryProductOptionValueIds
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        array $inventoryProductOptionValueIds,
    ): InventoryItem {
        return DB::transaction(function () use (
            $organization,
            $inventoryItem,
            $inventoryProductOptionValueIds,
        ): InventoryItem {
            $lockedItem = InventoryItem::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $inventoryProductOptionValueIds = array_values(
                array_unique($inventoryProductOptionValueIds),
            );

            if ($inventoryProductOptionValueIds !== []) {
                if ($lockedItem->inventory_product_id === null) {
                    throw ValidationException::withMessages([
                        'inventory_product_option_value_ids' => __(
                            'Assign this item to a product family before selecting option values.',
                        ),
                    ]);
                }

                $containedCount = InventoryProductOptionValue::query()
                    ->where('organization_id', $organization->getKey())
                    ->whereIn('id', $inventoryProductOptionValueIds)
                    ->whereHas(
                        'inventoryProductOption',
                        fn ($query) => $query->where(
                            'inventory_product_id',
                            $lockedItem->inventory_product_id,
                        ),
                    )
                    ->count();

                if ($containedCount !== count($inventoryProductOptionValueIds)) {
                    throw ValidationException::withMessages([
                        'inventory_product_option_value_ids' => __(
                            'Select option values from the item\'s own organization and product family.',
                        ),
                    ]);
                }
            }

            $lockedItem->optionValueAssociations()
                ->whereNotIn(
                    'inventory_product_option_value_id',
                    $inventoryProductOptionValueIds,
                )
                ->delete();

            $existingIds = $lockedItem
                ->optionValueAssociations()
                ->pluck('inventory_product_option_value_id')
                ->all();

            foreach (
                array_diff($inventoryProductOptionValueIds, $existingIds) as $valueId
            ) {
                $lockedItem->optionValueAssociations()->create([
                    'organization_id' => $organization->getKey(),
                    'inventory_product_option_value_id' => $valueId,
                ]);
            }

            return $lockedItem;
        });
    }
}
