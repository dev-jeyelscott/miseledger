<?php

namespace App\Actions\Inventory;

use App\Enums\BarcodeSymbology;
use App\Models\Barcode;
use App\Models\InventoryItem;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final class CreateBarcode
{
    /**
     * Register a barcode identity for an item or one of its alternate units.
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        string $value,
        BarcodeSymbology $symbology,
        ?int $inventoryItemUnitId,
        bool $isPrimary,
        bool $active,
    ): Barcode {
        return DB::transaction(function () use (
            $organization,
            $inventoryItem,
            $value,
            $symbology,
            $inventoryItemUnitId,
            $isPrimary,
            $active,
        ): Barcode {
            $lockedItem = InventoryItem::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($inventoryItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($isPrimary) {
                $lockedItem
                    ->barcodes()
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            return $lockedItem->barcodes()->create([
                'organization_id' => $organization->getKey(),
                'inventory_item_unit_id' => $inventoryItemUnitId,
                'value' => $value,
                'symbology' => $symbology,
                'is_primary' => $isPrimary,
                'active' => $active,
            ]);
        });
    }
}
