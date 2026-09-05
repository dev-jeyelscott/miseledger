<?php

namespace App\Actions\Inventory;

use App\Enums\BarcodeSymbology;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateBarcode
{
    /**
     * Update a barcode's identity, unit association, and state.
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        InventoryItemBarcode $barcode,
        string $value,
        BarcodeSymbology $symbology,
        ?int $inventoryItemUnitId,
        bool $isPrimary,
        bool $active,
    ): InventoryItemBarcode {
        if ($isPrimary && ! $active) {
            throw ValidationException::withMessages([
                'active' => __('A primary barcode must be active.'),
            ]);
        }

        return DB::transaction(function () use (
            $organization,
            $inventoryItem,
            $barcode,
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

            $lockedBarcode = $lockedItem
                ->barcodes()
                ->whereKey($barcode->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $resolvedUnitId = $inventoryItemUnitId === null
                ? null
                : (int) $lockedItem
                    ->unitConversions()
                    ->whereKey($inventoryItemUnitId)
                    ->firstOrFail()
                    ->getKey();

            if ($isPrimary) {
                $lockedItem
                    ->barcodes()
                    ->where('primary', true)
                    ->whereKeyNot($lockedBarcode->getKey())
                    ->update(['primary' => false]);
            }

            $lockedBarcode->update([
                'inventory_item_unit_id' => $resolvedUnitId,
                'barcode' => $value,
                'symbology' => $symbology,
                'primary' => $isPrimary,
                'active' => $active,
            ]);

            return $lockedBarcode;
        }, attempts: 3);
    }
}
