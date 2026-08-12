<?php

namespace App\Actions\Suppliers;

use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\SupplierItemPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordSupplierItemPrice
{
    /**
     * Append a supplier price and update the cached current price atomically.
     */
    public function handle(
        Organization $organization,
        SupplierItem $supplierItem,
        string $price,
    ): SupplierItemPrice {
        return DB::transaction(function () use (
            $organization,
            $supplierItem,
            $price,
        ): SupplierItemPrice {
            $lockedSupplierItem = SupplierItem::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($supplierItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $supplier = Supplier::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($lockedSupplierItem->supplier_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $supplier->active || ! $lockedSupplierItem->active) {
                throw ValidationException::withMessages([
                    'price' => __(
                        'Prices can only be added to an active supplier item.',
                    ),
                ]);
            }

            $supplierItemPrice = $lockedSupplierItem->prices()->create([
                'organization_id' => $organization->id,
                'price' => $price,
                'currency' => $organization->currency,
                'effective_at' => now(),
            ]);

            $lockedSupplierItem->forceFill([
                'current_price' => $supplierItemPrice->price,
                'currency' => $supplierItemPrice->currency,
            ])->save();

            return $supplierItemPrice;
        });
    }
}
