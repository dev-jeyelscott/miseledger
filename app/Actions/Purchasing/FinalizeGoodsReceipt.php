<?php

namespace App\Actions\Purchasing;

use App\Actions\Audit\RecordAuditEntry;
use App\Actions\Inventory\LockedDependencies;
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
                ->whereKey($receipt->location_id)
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if ($location === null) {
                throw ValidationException::withMessages([
                    'location' => __('Select an active location from the current organization.'),
                ]);
            }

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

            $lockedDependencies = $this->preloadDependencies(
                $organization,
                $purchaseOrder,
                $location,
                $lines,
                $nonStockLines,
            );

            $finalizedAt = now();

            $movementEntries = [];
            $receivedQuantities = [];

            foreach ($lines as $receiptLine) {
                $poLine = $lockedDependencies->purchaseOrderLine(
                    $receiptLine->purchase_order_line_id,
                );

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

                $currentReceivedQuantity = $receivedQuantities[$poLine->id]
                    ?? BigDecimal::of($poLine->received_base_quantity);

                $newReceivedQuantity = $currentReceivedQuantity->plus(
                    BigDecimal::of($receiptLine->base_quantity),
                )->toScale(6, RoundingMode::HalfUp);

                $inventoryItem = $lockedDependencies->inventoryItem(
                    $receiptLine->inventory_item_id,
                );

                $baseUnit = $lockedDependencies->baseUnit(
                    $inventoryItem->base_unit_of_measure_id,
                );

                $storageLocation = $lockedDependencies->storageLocation(
                    $receiptLine->storage_location_id,
                );

                $movementEntries[] = [
                    'storageLocation' => $storageLocation,
                    'inventoryItem' => $inventoryItem,
                    'type' => StockMovementType::PurchaseReceipt,
                    'baseQuantity' => $receiptLine->base_quantity,
                    'baseUnitOfMeasure' => $baseUnit,
                    'referenceType' => 'goods_receipt_line',
                    'referenceId' => $receiptLine->id,
                    'occurredAt' => $finalizedAt,
                    'actor' => $actor,
                    'idempotencyKey' => "goods_receipt:{$receipt->id}:line:{$receiptLine->id}",
                    'notes' => "Goods receipt {$receipt->number}",
                    'inboundUnitCost' => $receiptLine->unit_cost,
                ];
                $receivedQuantities[$poLine->id] = $newReceivedQuantity;
            }

            $this->recordStockMovement->handleBatch(
                organization: $organization,
                location: $location,
                movements: $movementEntries,
                lockedDependencies: $lockedDependencies,
            );

            foreach ($receivedQuantities as $poLineId => $receivedQuantity) {
                $lockedDependencies->purchaseOrderLine($poLineId)->forceFill([
                    'received_base_quantity' => (string) $receivedQuantity,
                ])->save();
            }

            foreach ($nonStockLines as $nonStockLine) {
                $this->validateNonStockEvidence(
                    $organization,
                    $lines,
                    $nonStockLine,
                    $lockedDependencies,
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
     * Resolve and lock every dependency needed by accepted receipt lines.
     *
     * @param  EloquentCollection<int, GoodsReceiptLine>  $lines
     * @param  EloquentCollection<int, GoodsReceiptNonStockLine>  $nonStockLines
     */
    private function preloadDependencies(
        Organization $organization,
        PurchaseOrder $purchaseOrder,
        Location $location,
        EloquentCollection $lines,
        EloquentCollection $nonStockLines,
    ): LockedDependencies {
        if (! Organization::query()
            ->whereKey($organization->id)
            ->where('active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'organization' => __('The active organization is disabled.'),
            ]);
        }

        $purchaseOrderLineIds = $lines
            ->pluck('purchase_order_line_id')
            ->merge($nonStockLines->pluck('purchase_order_line_id'))
            ->unique()
            ->values();

        $purchaseOrderLines = PurchaseOrderLine::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereIn('id', $purchaseOrderLineIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($purchaseOrderLines->count() !== $purchaseOrderLineIds->count()) {
            throw ValidationException::withMessages([
                'lines' => __('A receipt line must reference a line from its purchase order.'),
            ]);
        }

        $storageLocationIds = $lines
            ->pluck('storage_location_id')
            ->unique()
            ->values();

        $storageLocations = StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $purchaseOrder->location_id)
            ->whereIn('id', $storageLocationIds)
            ->where('active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($storageLocations->count() !== $storageLocationIds->count()) {
            throw ValidationException::withMessages([
                'storage_location' => __('The storage location does not belong to the selected active location.'),
            ]);
        }

        $inventoryItemIds = $lines
            ->pluck('inventory_item_id')
            ->merge($nonStockLines->pluck('inventory_item_id'))
            ->unique()
            ->values();

        $inventoryItems = InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $inventoryItemIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($inventoryItems->count() !== $inventoryItemIds->count()) {
            throw ValidationException::withMessages([
                'lines' => __('A receipt line must reference an inventory item from the current organization.'),
            ]);
        }

        foreach ($lines as $line) {
            $item = $inventoryItems->get($line->inventory_item_id);

            if ($item === null || ! $item->active) {
                throw ValidationException::withMessages([
                    'inventory_item' => __('Select an active inventory item from the current organization.'),
                ]);
            }
        }

        $baseUnitIds = $lines
            ->map(fn (GoodsReceiptLine $line): ?int => $inventoryItems
                ->get($line->inventory_item_id)?->base_unit_of_measure_id)
            ->filter()
            ->unique()
            ->values();

        $baseUnits = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $baseUnitIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($baseUnits->count() !== $baseUnitIds->count()) {
            throw ValidationException::withMessages([
                'base_unit_of_measure_id' => __('The inventory item must have an active base unit before stock can move.'),
            ]);
        }

        foreach ($baseUnits as $baseUnit) {
            if (! $baseUnit->active) {
                throw ValidationException::withMessages([
                    'base_unit_of_measure_id' => __('The inventory item must have an active base unit before stock can move.'),
                ]);
            }
        }

        return new LockedDependencies(
            location: $location,
            purchaseOrderLines: $purchaseOrderLines,
            inventoryItems: $inventoryItems,
            baseUnits: $baseUnits,
            storageLocations: $storageLocations,
            actorMembershipValidated: true,
        );
    }

    /**
     * Require rejected/damaged evidence to remain tenant-safe and stock-neutral.
     *
     * @param  Collection<int, GoodsReceiptLine>  $acceptedLines
     */
    private function validateNonStockEvidence(
        Organization $organization,
        EloquentCollection $acceptedLines,
        GoodsReceiptNonStockLine $nonStockLine,
        LockedDependencies $lockedDependencies,
    ): void {
        $poLine = $lockedDependencies->purchaseOrderLine(
            $nonStockLine->purchase_order_line_id,
        );

        if ($poLine->inventory_item_id !== $nonStockLine->inventory_item_id) {
            throw ValidationException::withMessages([
                'lines' => __(
                    'Non-stock receiving evidence does not match its purchase-order line.',
                ),
            ]);
        }

        $lockedDependencies->inventoryItem($nonStockLine->inventory_item_id);

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
