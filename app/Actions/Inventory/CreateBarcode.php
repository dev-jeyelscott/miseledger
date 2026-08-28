<?php

namespace App\Actions\Inventory;

use App\Enums\BarcodeSymbology;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final class CreateBarcode
{
    /**
     * Register a barcode identity for an item or alternate unit.
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        string $value,
        BarcodeSymbology $symbology,
        ?int $inventoryItemUnitId,
        bool $isPrimary,
        bool $active,
    ): InventoryItemBarcode {
        return DB::transaction(function () use (
            $organization,
            $inventoryItem,
            $value,
            $symbology,
            $inventoryItemUnitId,
            $isPrimary,
            $active,
        ): InventoryItemBarcode {
            $lockedItem = InventoryItem::query()
                ->where(
                    'organization_id',
                    $organization->getKey(),
                )
                ->whereKey($inventoryItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($isPrimary) {
                $lockedItem
                    ->barcodes()
                    ->where('primary', true)
                    ->update(['primary' => false]);
            }

            return $lockedItem->barcodes()->create([
                'organization_id' => $organization->getKey(),
                'inventory_item_unit_id' => $inventoryItemUnitId,
                'barcode' => $value,
                'symbology' => $symbology,
                'primary' => $isPrimary,
                'active' => $active,
            ]);
        }, attempts: 3);
    }
}
