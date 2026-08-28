<?php

namespace App\Actions\Inventory;

use App\Enums\BarcodeSymbology;
use App\Models\Barcode;
use App\Models\InventoryItem;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final class UpdateBarcode
{
    /**
     * Update a barcode's identity, association, and activation state.
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        Barcode $barcode,
        string $value,
        BarcodeSymbology $symbology,
        ?int $inventoryItemUnitId,
        bool $isPrimary,
        bool $active,
    ): Barcode {
        return DB::transaction(function () use (
            $organization,
            $inventoryItem,
            $barcode,
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

            $lockedBarcode = $lockedItem
                ->barcodes()
                ->whereKey($barcode->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($isPrimary) {
                $lockedItem
                    ->barcodes()
                    ->where('is_primary', true)
                    ->whereKeyNot($lockedBarcode->getKey())
                    ->update(['is_primary' => false]);
            }

            $lockedBarcode->update([
                'inventory_item_unit_id' => $inventoryItemUnitId,
                'value' => $value,
                'symbology' => $symbology,
                'is_primary' => $isPrimary,
                'active' => $active,
            ]);

            return $lockedBarcode;
        });
    }
}
