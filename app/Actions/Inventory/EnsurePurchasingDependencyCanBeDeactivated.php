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
        $this->assertNotOnlyActiveItem($organization, $inventoryItem);

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

    /**
     * Prevent deactivating an organization's last active inventory item,
     * since that would lock every operational stock workflow org-wide with
     * no item left to record activity against. Callers must lock the
     * organization row for the duration of the transaction so this check is
     * race-safe against a concurrent deactivation of a different item.
     */
    private function assertNotOnlyActiveItem(
        Organization $organization,
        InventoryItem $inventoryItem,
    ): void {
        $anotherActiveItemExists = InventoryItem::query()
            ->where('organization_id', $organization->getKey())
            ->where('active', true)
            ->whereKeyNot($inventoryItem->getKey())
            ->exists();

        if ($anotherActiveItemExists) {
            return;
        }

        throw ValidationException::withMessages([
            'active' => __(
                'This is the organization\'s only active inventory item. Add or reactivate another item before deactivating this one, or every purchasing, receiving, stock count, transfer, waste, and adjustment workflow will be locked until one is available.',
            ),
        ]);
    }
}
