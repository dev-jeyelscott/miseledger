<?php

namespace App\Actions\Purchasing;

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinalizeGoodsReceipt
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    /**
     * Finalize one receipt and all inventory effects atomically.
     */
    public function handle(
        Organization $organization,
        User $actor,
        GoodsReceipt $goodsReceipt,
    ): GoodsReceipt {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $goodsReceipt,
        ): GoodsReceipt {
            $this->authorize($organization, $actor);

            $receipt = GoodsReceipt::query()
                ->where('organization_id', $organization->id)
                ->whereKey($goodsReceipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($receipt->status === GoodsReceiptStatus::Finalized) {
                return $receipt;
            }

            if ($receipt->status !== GoodsReceiptStatus::Draft) {
                throw ValidationException::withMessages([
                    'goods_receipt' => __(
                        'Only a draft goods receipt can be finalized.',
                    ),
                ]);
            }

            $purchaseOrder = PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->whereKey($receipt->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! $purchaseOrder->status->canReceive()
                && $purchaseOrder->status !== PurchaseOrderStatus::Received
            ) {
                throw ValidationException::withMessages([
                    'purchase_order' => __(
                        'This purchase order is no longer open for this goods-receipt workflow.',
                    ),
                ]);
            }

            $location = Location::query()
                ->where('organization_id', $organization->id)
                ->findOrFail($receipt->location_id);

            $lines = GoodsReceiptLine::query()
                ->where('goods_receipt_id', $receipt->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'A goods receipt requires at least one line before finalization.',
                    ),
                ]);
            }

            $finalizedAt = now();

            foreach ($lines as $receiptLine) {
                $poLine = PurchaseOrderLine::query()
                    ->where(
                        'purchase_order_id',
                        $purchaseOrder->id,
                    )
                    ->whereKey($receiptLine->purchase_order_line_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $poLine->inventory_item_id
                    !== $receiptLine->inventory_item_id
                ) {
                    throw ValidationException::withMessages([
                        'lines' => __(
                            'Receipt line inventory does not match its purchase-order line.',
                        ),
                    ]);
                }

                $newReceivedQuantity = BigDecimal::of(
                    $poLine->received_base_quantity,
                )->plus(
                    BigDecimal::of($receiptLine->base_quantity),
                )->toScale(6, RoundingMode::HalfUp);

                $inventoryItem = InventoryItem::query()
                    ->where('organization_id', $organization->id)
                    ->findOrFail($receiptLine->inventory_item_id);

                $baseUnit = UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->findOrFail(
                        $inventoryItem->base_unit_of_measure_id,
                    );

                $storageLocation = StorageLocation::query()
                    ->where('organization_id', $organization->id)
                    ->where('location_id', $purchaseOrder->location_id)
                    ->findOrFail($receiptLine->storage_location_id);

                $this->recordStockMovement->handle(
                    organization: $organization,
                    location: $location,
                    storageLocation: $storageLocation,
                    inventoryItem: $inventoryItem,
                    type: StockMovementType::PurchaseReceipt,
                    baseQuantity: $receiptLine->base_quantity,
                    baseUnitOfMeasure: $baseUnit,
                    referenceType: 'goods_receipt_line',
                    referenceId: $receiptLine->id,
                    occurredAt: $finalizedAt,
                    actor: $actor,
                    idempotencyKey: "goods_receipt:{$receipt->id}:line:{$receiptLine->id}",
                    notes: "Goods receipt {$receipt->number}",
                    inboundUnitCost: $receiptLine->unit_cost,
                );

                $poLine->forceFill([
                    'received_base_quantity' => (string) $newReceivedQuantity,
                ])->save();
            }

            $hasRemainingQuantity = PurchaseOrderLine::query()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->whereColumn(
                    'received_base_quantity',
                    '<',
                    'base_quantity',
                )
                ->exists();

            $purchaseOrder->forceFill([
                'status' => $hasRemainingQuantity
                    ? PurchaseOrderStatus::PartiallyReceived
                    : PurchaseOrderStatus::Received,
            ])->save();

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::Finalized,
                'received_at' => $finalizedAt,
                'received_by' => $actor->id,
            ])->save();

            AuditLog::query()->create([
                'organization_id' => $organization->id,
                'actor_id' => $actor->id,
                'action' => 'goods_receipt.finalized',
                'entity_type' => 'goods_receipt',
                'entity_id' => $receipt->id,
                'before_data' => [
                    'status' => GoodsReceiptStatus::Draft->value,
                ],
                'after_data' => [
                    'status' => GoodsReceiptStatus::Finalized->value,
                    'purchase_order_id' => $purchaseOrder->id,
                    'received_at' => $finalizedAt->toIso8601String(),
                    'line_count' => $lines->count(),
                ],
                'correlation_id' => "goods_receipt:{$receipt->id}:finalize",
            ]);

            return $receipt->refresh();
        }, 3);
    }

    /**
     * Require permission to finalize receiving.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::ReceivingFinalize,
            )
        ) {
            abort(403);
        }
    }
}
