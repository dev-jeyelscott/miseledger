<?php

namespace App\Actions\Purchasing;

use App\Actions\Audit\RecordAuditEntry;
use App\Actions\Inventory\RecordStockMovement;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNonStockLine;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinalizeGoodsReceipt
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /**
     * Finalize one receipt and all accepted inventory effects atomically.
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

            if ($receipt->location_id !== $purchaseOrder->location_id) {
                throw ValidationException::withMessages([
                    'goods_receipt' => __(
                        'The goods receipt location does not match its purchase order.',
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

            $nonStockLines = GoodsReceiptNonStockLine::query()
                ->where('goods_receipt_id', $receipt->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty() && $nonStockLines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'A goods receipt requires at least one accepted, rejected, or damaged quantity before finalization.',
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

            foreach ($nonStockLines as $nonStockLine) {
                $this->validateNonStockEvidence(
                    $organization,
                    $purchaseOrder,
                    $lines,
                    $nonStockLine,
                );
            }

            $hasRemainingQuantity = PurchaseOrderLine::query()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->whereColumn(
                    'received_base_quantity',
                    '<',
                    'base_quantity',
                )
                ->exists();

            $hasAcceptedQuantity = PurchaseOrderLine::query()
                ->where('purchase_order_id', $purchaseOrder->id)
                ->where('received_base_quantity', '>', 0)
                ->exists();

            $purchaseOrder->forceFill([
                'status' => ! $hasRemainingQuantity
                    ? PurchaseOrderStatus::Received
                    : ($hasAcceptedQuantity
                        ? PurchaseOrderStatus::PartiallyReceived
                        : PurchaseOrderStatus::Approved),
            ])->save();

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::Finalized,
                'received_at' => $finalizedAt,
                'received_by' => $actor->id,
            ])->save();

            $this->recordAuditEntry->handle(
                organization: $organization,
                actor: $actor,
                action: 'goods_receipt.finalized',
                entityType: 'goods_receipt',
                entityId: $receipt->id,
                beforeData: [
                    'status' => GoodsReceiptStatus::Draft->value,
                ],
                afterData: [
                    'status' => GoodsReceiptStatus::Finalized->value,
                    'purchase_order_id' => $purchaseOrder->id,
                    'received_at' => $finalizedAt->toIso8601String(),
                    'line_count' => $lines->count(),
                    'non_stock_line_count' => $nonStockLines->count(),
                ],
                correlationId: "goods_receipt:{$receipt->id}:finalize",
            );

            return $receipt->refresh();
        }, 3);
    }

    /**
     * Require rejected/damaged evidence to remain tenant-safe and stock-neutral.
     *
     * @param  Collection<int, GoodsReceiptLine>  $acceptedLines
     */
    private function validateNonStockEvidence(
        Organization $organization,
        PurchaseOrder $purchaseOrder,
        EloquentCollection $acceptedLines,
        GoodsReceiptNonStockLine $nonStockLine,
    ): void {
        $poLine = PurchaseOrderLine::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereKey($nonStockLine->purchase_order_line_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($poLine->inventory_item_id !== $nonStockLine->inventory_item_id) {
            throw ValidationException::withMessages([
                'lines' => __(
                    'Non-stock receiving evidence does not match its purchase-order line.',
                ),
            ]);
        }

        InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($nonStockLine->inventory_item_id);

        if ($nonStockLine->goods_receipt_line_id !== null) {
            $acceptedLine = $acceptedLines->firstWhere(
                'id',
                $nonStockLine->goods_receipt_line_id,
            );

            if (
                ! $acceptedLine instanceof GoodsReceiptLine
                || $acceptedLine->purchase_order_line_id !== $poLine->id
                || $acceptedLine->inventory_item_id !== $poLine->inventory_item_id
            ) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'Non-stock receiving evidence is not linked to its matching accepted receipt line.',
                    ),
                ]);
            }
        }

        $hasRejected = $this->validateEvidenceQuantity(
            $organization,
            $nonStockLine->rejected_quantity,
            $nonStockLine->rejected_base_quantity,
            $nonStockLine->rejected_unit_of_measure_id,
        );

        $hasDamaged = $this->validateEvidenceQuantity(
            $organization,
            $nonStockLine->damaged_quantity,
            $nonStockLine->damaged_base_quantity,
            $nonStockLine->damaged_unit_of_measure_id,
        );

        if (! $hasRejected && ! $hasDamaged) {
            throw ValidationException::withMessages([
                'lines' => __(
                    'Non-stock receiving evidence requires a rejected or damaged quantity.',
                ),
            ]);
        }
    }

    /**
     * Validate one immutable non-stock quantity/UOM/base snapshot triple.
     */
    private function validateEvidenceQuantity(
        Organization $organization,
        ?string $quantity,
        ?string $baseQuantity,
        ?int $unitOfMeasureId,
    ): bool {
        if (
            $quantity === null
            && $baseQuantity === null
            && $unitOfMeasureId === null
        ) {
            return false;
        }

        if (
            $quantity === null
            || $baseQuantity === null
            || $unitOfMeasureId === null
            || BigDecimal::of($quantity)->compareTo(BigDecimal::zero()) <= 0
            || BigDecimal::of($baseQuantity)->compareTo(BigDecimal::zero()) <= 0
        ) {
            throw ValidationException::withMessages([
                'lines' => __(
                    'Rejected and damaged evidence must contain positive quantity and base snapshots with a unit.',
                ),
            ]);
        }

        UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($unitOfMeasureId);

        return true;
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
