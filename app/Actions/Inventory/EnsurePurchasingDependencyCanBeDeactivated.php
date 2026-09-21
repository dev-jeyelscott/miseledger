<?php

namespace App\Actions\Inventory;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Validation\ValidationException;

final class EnsurePurchasingDependencyCanBeDeactivated
{
    /**
     * Prevent deactivating an inventory item still required by an open
     * approved/partially received purchase-order line, or by a draft goods
     * receipt line that can still produce stock on finalization.
     */
    public function assertInventoryItemCanBeDeactivated(
        Organization $organization,
        InventoryItem $inventoryItem,
    ): void {
        $hasOpenPurchaseOrderLine = PurchaseOrderLine::query()
            ->where('inventory_item_id', $inventoryItem->getKey())
            ->whereIn(
                'purchase_order_id',
                PurchaseOrder::query()
                    ->select('id')
                    ->where('organization_id', $organization->getKey())
                    ->whereIn('status', [
                        PurchaseOrderStatus::Approved->value,
                        PurchaseOrderStatus::PartiallyReceived->value,
                    ]),
            )
            ->whereRaw(
                'received_base_quantity + rejected_damaged_base_quantity < base_quantity',
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->exists();

        if ($hasOpenPurchaseOrderLine) {
            throw ValidationException::withMessages([
                'active' => __(
                    'This inventory item cannot be deactivated while it is referenced by an open approved purchase-order line.',
                ),
            ]);
        }

        $hasDraftGoodsReceiptLine = GoodsReceiptLine::query()
            ->where('inventory_item_id', $inventoryItem->getKey())
            ->whereIn(
                'goods_receipt_id',
                GoodsReceipt::query()
                    ->select('id')
                    ->where('organization_id', $organization->getKey())
                    ->where('status', GoodsReceiptStatus::Draft->value),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->exists();

        if ($hasDraftGoodsReceiptLine) {
            throw ValidationException::withMessages([
                'active' => __(
                    'This inventory item cannot be deactivated while it is included in a draft goods receipt.',
                ),
            ]);
        }
    }
}
