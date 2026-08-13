<?php

namespace App\Actions\Purchasing;

use App\Actions\Inventory\ConvertQuantity;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationPermission;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
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

final class SaveGoodsReceipt
{
    public function __construct(
        private readonly ConvertQuantity $convertQuantity,
    ) {}

    /**
     * Create or replace one receipt draft without creating inventory.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Organization $organization,
        User $actor,
        PurchaseOrder $purchaseOrder,
        array $attributes,
        ?GoodsReceipt $goodsReceipt = null,
    ): GoodsReceipt {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $purchaseOrder,
            $attributes,
            $goodsReceipt,
        ): GoodsReceipt {
            $this->authorize($organization, $actor);

            $po = PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $po->status->canReceive()) {
                throw ValidationException::withMessages([
                    'purchase_order' => __(
                        'Goods can only be received against an approved purchase order with remaining quantity.',
                    ),
                ]);
            }

            $receipt = $goodsReceipt === null
                ? null
                : GoodsReceipt::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($goodsReceipt->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $receipt !== null
                && ! $receipt->status->isEditable()
            ) {
                throw ValidationException::withMessages([
                    'goods_receipt' => __(
                        'Only draft goods receipts can be edited.',
                    ),
                ]);
            }

            if (
                $receipt !== null
                && $receipt->purchase_order_id !== $po->id
            ) {
                abort(403);
            }

            $rawLines = $attributes['lines'] ?? [];

            if (! is_array($rawLines) || $rawLines === []) {
                throw ValidationException::withMessages([
                    'lines' => __('At least one receipt line is required.'),
                ]);
            }

            $lineSnapshots = [];
            $receiptTotalsByPoLine = [];

            foreach (array_values($rawLines) as $index => $rawLine) {
                if (! is_array($rawLine)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}" => __('Invalid receipt line.'),
                    ]);
                }

                $poLine = PurchaseOrderLine::query()
                    ->with([
                        'inventoryItem.baseUnitOfMeasure',
                        'purchaseUnitOfMeasure',
                    ])
                    ->where('purchase_order_id', $po->id)
                    ->find(
                        (int) (
                            $rawLine['purchase_order_line_id'] ?? 0
                        ),
                    );

                if ($poLine === null) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.purchase_order_line_id" => __(
                            'Select a purchase-order line from this purchase order.',
                        ),
                    ]);
                }

                $storageLocation = StorageLocation::query()
                    ->where('organization_id', $organization->id)
                    ->where('location_id', $po->location_id)
                    ->where('active', true)
                    ->find(
                        (int) (
                            $rawLine['storage_location_id'] ?? 0
                        ),
                    );

                if ($storageLocation === null) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.storage_location_id" => __(
                            'Select an active storage location belonging to the purchase-order location.',
                        ),
                    ]);
                }

                $receivedUnit = UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->where('active', true)
                    ->find(
                        (int) (
                            $rawLine['received_unit_of_measure_id'] ?? 0
                        ),
                    );

                if ($receivedUnit === null) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.received_unit_of_measure_id" => __(
                            'Select an active receiving unit.',
                        ),
                    ]);
                }

                $receivedQuantity = $this->positiveQuantity(
                    $rawLine['received_quantity'] ?? null,
                    "lines.{$index}.received_quantity",
                );

                $baseQuantity = $this->baseQuantity(
                    $organization,
                    $poLine,
                    $receivedQuantity,
                    $receivedUnit,
                );

                $poLineId = $poLine->id;

                $accumulated = (
                    $receiptTotalsByPoLine[$poLineId]
                    ?? BigDecimal::zero()
                )->plus($baseQuantity);

                $receiptTotalsByPoLine[$poLineId] = $accumulated;

                $remaining = BigDecimal::of($poLine->base_quantity)
                    ->minus(
                        BigDecimal::of(
                            $poLine->received_base_quantity,
                        ),
                    );

                if ($accumulated->compareTo($remaining) > 0) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.received_quantity" => __(
                            'Received quantity exceeds the remaining purchase-order quantity.',
                        ),
                    ]);
                }

                $basePerPurchaseUnit = BigDecimal::of(
                    $poLine->base_quantity,
                )->dividedBy(
                    BigDecimal::of($poLine->ordered_quantity),
                    12,
                    RoundingMode::HalfUp,
                );

                $unitCost = BigDecimal::of($poLine->unit_price)
                    ->dividedBy(
                        $basePerPurchaseUnit,
                        4,
                        RoundingMode::HalfUp,
                    );

                $totalCost = $baseQuantity
                    ->multipliedBy($unitCost)
                    ->toScale(4, RoundingMode::HalfUp);

                $lineSnapshots[] = [
                    'purchase_order_line_id' => $poLine->id,
                    'inventory_item_id' => $poLine->inventory_item_id,
                    'storage_location_id' => $storageLocation->id,
                    'received_quantity' => (string) $receivedQuantity
                        ->toScale(6, RoundingMode::HalfUp),
                    'received_unit_of_measure_id' => $receivedUnit->id,
                    'base_quantity' => (string) $baseQuantity,
                    'unit_cost' => (string) $unitCost,
                    'total_cost' => (string) $totalCost,
                    'notes' => $this->nullableString(
                        $rawLine['notes'] ?? null,
                    ),
                ];
            }

            $values = [
                'organization_id' => $organization->id,
                'location_id' => $po->location_id,
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'number' => (string) $attributes['number'],
                'supplier_reference' => $attributes[
                    'supplier_reference'
                ] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ];

            if ($receipt === null) {
                $receipt = GoodsReceipt::query()->create([
                    ...$values,
                    'status' => GoodsReceiptStatus::Draft,
                    'received_at' => null,
                    'received_by' => null,
                ]);
            } else {
                $receipt->fill($values);
                $receipt->save();

                $receipt->lines()->delete();
            }

            $receipt->lines()->createMany($lineSnapshots);

            return $receipt->refresh();
        }, 3);
    }

    /**
     * Require receiving permission.
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

    /**
     * Convert an entered receipt quantity to the PO line's base quantity.
     *
     * Purchase-unit receiving uses the immutable PO snapshots rather than
     * mutable supplier-item configuration.
     */
    private function baseQuantity(
        Organization $organization,
        PurchaseOrderLine $poLine,
        BigDecimal $receivedQuantity,
        UnitOfMeasure $receivedUnit,
    ): BigDecimal {
        if (
            $receivedUnit->id
            === $poLine->purchase_unit_of_measure_id
        ) {
            $basePerPurchaseUnit = BigDecimal::of(
                $poLine->base_quantity,
            )->dividedBy(
                BigDecimal::of($poLine->ordered_quantity),
                12,
                RoundingMode::HalfUp,
            );

            return $receivedQuantity
                ->multipliedBy($basePerPurchaseUnit)
                ->toScale(6, RoundingMode::HalfUp);
        }

        $inventoryItem = $poLine->inventoryItem;

        if (! $inventoryItem instanceof InventoryItem) {
            throw ValidationException::withMessages([
                'inventory_item' => __('Inventory item is unavailable.'),
            ]);
        }

        $converted = $this->convertQuantity->handle(
            $organization,
            $inventoryItem,
            (string) $receivedQuantity,
            $receivedUnit,
            $inventoryItem->baseUnitOfMeasure,
        );

        return BigDecimal::of($converted)
            ->toScale(6, RoundingMode::HalfUp);
    }

    /**
     * Parse and require a positive quantity.
     */
    private function positiveQuantity(
        mixed $value,
        string $field,
    ): BigDecimal {
        try {
            $quantity = BigDecimal::of((string) $value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $field => __('A valid quantity is required.'),
            ]);
        }

        if ($quantity->compareTo(BigDecimal::zero()) <= 0) {
            throw ValidationException::withMessages([
                $field => __('Quantity must be greater than zero.'),
            ]);
        }

        return $quantity;
    }

    /**
     * Normalize optional line text.
     */
    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
