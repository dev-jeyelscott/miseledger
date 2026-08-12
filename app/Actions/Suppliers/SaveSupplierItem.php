<?php

namespace App\Actions\Suppliers;

use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveSupplierItem
{
    /**
     * Save a tenant-safe supplier purchase-pack mapping.
     *
     * @param  array{
     *     inventory_item_id: int,
     *     supplier_sku: string,
     *     description: string|null,
     *     purchase_unit_of_measure_id: int,
     *     base_quantity: string,
     *     active: bool
     * }  $attributes
     */
    public function handle(
        Organization $organization,
        Supplier $supplier,
        array $attributes,
        ?SupplierItem $supplierItem = null,
    ): SupplierItem {
        return DB::transaction(function () use (
            $organization,
            $supplier,
            $attributes,
            $supplierItem,
        ): SupplierItem {
            $lockedSupplier = Supplier::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($supplier->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $inventoryItem = InventoryItem::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($attributes['inventory_item_id'])
                ->lockForUpdate()
                ->first();

            if ($inventoryItem === null) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => __(
                        'Select an inventory item from the current organization.',
                    ),
                ]);
            }

            $purchaseUnit = UnitOfMeasure::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($attributes['purchase_unit_of_measure_id'])
                ->lockForUpdate()
                ->first();

            if ($purchaseUnit === null) {
                throw ValidationException::withMessages([
                    'purchase_unit_of_measure_id' => __(
                        'Select a unit from the current organization.',
                    ),
                ]);
            }

            if ($attributes['active'] && ! $lockedSupplier->active) {
                throw ValidationException::withMessages([
                    'active' => __(
                        'An active supplier item requires an active supplier.',
                    ),
                ]);
            }

            if ($attributes['active'] && ! $inventoryItem->active) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => __(
                        'An active supplier item requires an active inventory item.',
                    ),
                ]);
            }

            if ($attributes['active'] && ! $purchaseUnit->active) {
                throw ValidationException::withMessages([
                    'purchase_unit_of_measure_id' => __(
                        'An active supplier item requires an active purchase unit.',
                    ),
                ]);
            }

            $attributes['inventory_item_id'] = $inventoryItem->id;
            $attributes['purchase_unit_of_measure_id'] = $purchaseUnit->id;

            if ($supplierItem === null) {
                return $lockedSupplier->supplierItems()->create([
                    ...$attributes,
                    'organization_id' => $organization->id,
                    'currency' => $organization->currency,
                ]);
            }

            $lockedSupplierItem = SupplierItem::query()
                ->where('organization_id', $organization->getKey())
                ->where('supplier_id', $lockedSupplier->getKey())
                ->whereKey($supplierItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSupplierItem->update($attributes);

            return $lockedSupplierItem;
        });
    }
}
