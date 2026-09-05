<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItemBarcode;
use App\Models\Organization;
use App\Support\Inventory\BarcodeLookupResult;

final class LookupBarcode
{
    /**
     * Resolve an exact active barcode match within the organization.
     */
    public function handle(
        Organization $organization,
        string $value,
    ): BarcodeLookupResult {
        $barcode = InventoryItemBarcode::query()
            ->where(
                'organization_id',
                $organization->getKey(),
            )
            ->where('barcode', $value)
            ->where('active', true)
            ->whereHas(
                'inventoryItem',
                fn ($query) => $query->where('active', true),
            )
            ->where(function ($query): void {
                $query
                    ->whereNull('inventory_item_unit_id')
                    ->orWhereHas('inventoryItemUnit', function ($query): void {
                        $query
                            ->where('active', true)
                            ->whereHas(
                                'unitOfMeasure',
                                fn ($query) => $query->where('active', true),
                            );
                    });
            })
            ->with([
                'inventoryItem',
                'inventoryItemUnit.unitOfMeasure',
            ])
            ->first();

        if ($barcode === null) {
            return BarcodeLookupResult::notFound();
        }

        return BarcodeLookupResult::found($barcode);
    }
}
