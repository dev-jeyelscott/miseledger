<?php

namespace App\Actions\Purchasing;

use App\Actions\Inventory\ConvertQuantity;
use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
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

final class SaveGoodsReceipt
{
    private const MAX_QUANTITY = '999999999.999999';

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

            $this->validatePurchaseOrderLocation($organization, $po);

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

            if (
                $receipt !== null
                && $receipt->location_id !== $po->location_id
            ) {
                throw ValidationException::withMessages([
                    'goods_receipt' => __(
                        'The goods receipt location does not match its purchase order.',
                    ),
                ]);
            }

            if (
                ! $po->status->canReceive()
                && ! (
                    $receipt !== null
                    && $po->status === PurchaseOrderStatus::Received
                )
            ) {
                throw ValidationException::withMessages([
                    'purchase_order' => __(
                        'This purchase order is not available for this goods-receipt workflow.',
                    ),
                ]);
            }

            $rawLines = $attributes['lines'] ?? [];

            if (! is_array($rawLines) || $rawLines === []) {
                throw ValidationException::withMessages([
                    'lines' => __('At least one receipt line is required.'),
                ]);
            }

            $lineSnapshots = [];

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

                $this->validatePurchaseOrderLineOwnership(
                    $organization,
                    $poLine,
                    $index,
                );

                $acceptedQuantity = $this->nonNegativeQuantity(
                    $rawLine['received_quantity'] ?? null,
                    "lines.{$index}.received_quantity",
                );

                $rejectedQuantity = $this->nonNegativeQuantity(
                    $rawLine['rejected_quantity'] ?? '0',
                    "lines.{$index}.rejected_quantity",
                );

                $damagedQuantity = $this->nonNegativeQuantity(
                    $rawLine['damaged_quantity'] ?? '0',
                    "lines.{$index}.damaged_quantity",
                );

                $hasAccepted = $this->isPositive($acceptedQuantity);
                $hasRejected = $this->isPositive($rejectedQuantity);
                $hasDamaged = $this->isPositive($damagedQuantity);

                if (! $hasAccepted && ! $hasRejected && ! $hasDamaged) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.received_quantity" => __(
                            'Enter an accepted, rejected, or damaged quantity greater than zero.',
                        ),
                    ]);
                }

                $notes = $this->nullableString(
                    $rawLine['notes'] ?? null,
                );

                $stockSnapshot = null;
                $nonStockSnapshot = null;

                if ($hasAccepted) {
                    $storageLocation = $this->storageLocation(
                        $organization,
                        $po,
                        $rawLine['storage_location_id'] ?? null,
                        "lines.{$index}.storage_location_id",
                    );

                    $acceptedUnit = $this->activeUnitOfMeasure(
                        $organization,
                        $rawLine['received_unit_of_measure_id'] ?? null,
                        "lines.{$index}.received_unit_of_measure_id",
                    );

                    $acceptedBaseQuantity = $this->baseQuantity(
                        $organization,
                        $poLine,
                        $acceptedQuantity,
                        $acceptedUnit,
                        "lines.{$index}.received_quantity",
                    );

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

                    $totalCost = $acceptedBaseQuantity
                        ->multipliedBy($unitCost)
                        ->toScale(4, RoundingMode::HalfUp);

                    $stockSnapshot = [
                        'purchase_order_line_id' => $poLine->id,
                        'inventory_item_id' => $poLine->inventory_item_id,
                        'storage_location_id' => $storageLocation->id,
                        'received_quantity' => (string) $acceptedQuantity,
                        'received_unit_of_measure_id' => $acceptedUnit->id,
                        'base_quantity' => (string) $acceptedBaseQuantity,
                        'unit_cost' => (string) $unitCost,
                        'total_cost' => (string) $totalCost,
                        'notes' => $notes,
                    ];
                }

                $rejectedUnit = null;
                $rejectedBaseQuantity = null;

                if ($hasRejected) {
                    $rejectedUnit = $this->activeUnitOfMeasure(
                        $organization,
                        $rawLine['rejected_unit_of_measure_id'] ?? null,
                        "lines.{$index}.rejected_unit_of_measure_id",
                    );

                    $rejectedBaseQuantity = $this->baseQuantity(
                        $organization,
                        $poLine,
                        $rejectedQuantity,
                        $rejectedUnit,
                        "lines.{$index}.rejected_quantity",
                    );
                }

                $damagedUnit = null;
                $damagedBaseQuantity = null;

                if ($hasDamaged) {
                    $damagedUnit = $this->activeUnitOfMeasure(
                        $organization,
                        $rawLine['damaged_unit_of_measure_id'] ?? null,
                        "lines.{$index}.damaged_unit_of_measure_id",
                    );

                    $damagedBaseQuantity = $this->baseQuantity(
                        $organization,
                        $poLine,
                        $damagedQuantity,
                        $damagedUnit,
                        "lines.{$index}.damaged_quantity",
                    );
                }

                if ($hasRejected || $hasDamaged) {
                    $nonStockSnapshot = [
                        'purchase_order_line_id' => $poLine->id,
                        'inventory_item_id' => $poLine->inventory_item_id,
                        'rejected_quantity' => $hasRejected
                            ? (string) $rejectedQuantity
                            : null,
                        'rejected_unit_of_measure_id' => $rejectedUnit?->id,
                        'rejected_base_quantity' => $rejectedBaseQuantity === null
                            ? null
                            : (string) $rejectedBaseQuantity,
                        'damaged_quantity' => $hasDamaged
                            ? (string) $damagedQuantity
                            : null,
                        'damaged_unit_of_measure_id' => $damagedUnit?->id,
                        'damaged_base_quantity' => $damagedBaseQuantity === null
                            ? null
                            : (string) $damagedBaseQuantity,
                        'notes' => $notes,
                    ];
                }

                $lineSnapshots[] = [
                    'stock' => $stockSnapshot,
                    'non_stock' => $nonStockSnapshot,
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

                $receipt->nonStockLines()->delete();
                $receipt->lines()->delete();
            }

            foreach ($lineSnapshots as $lineSnapshot) {
                $receiptLine = $lineSnapshot['stock'] === null
                    ? null
                    : $receipt->lines()->create($lineSnapshot['stock']);

                if ($lineSnapshot['non_stock'] !== null) {
                    $receipt->nonStockLines()->create([
                        ...$lineSnapshot['non_stock'],
                        'goods_receipt_line_id' => $receiptLine?->id,
                    ]);
                }
            }

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
     * Require the purchase-order location to belong to the active tenant.
     */
    private function validatePurchaseOrderLocation(
        Organization $organization,
        PurchaseOrder $purchaseOrder,
    ): void {
        $locationExists = Location::query()
            ->where('organization_id', $organization->id)
            ->whereKey($purchaseOrder->location_id)
            ->exists();

        if (! $locationExists) {
            throw ValidationException::withMessages([
                'purchase_order' => __(
                    'The purchase-order location does not belong to the active organization.',
                ),
            ]);
        }
    }

    /**
     * Require PO line item and UOM snapshots to remain tenant-owned.
     */
    private function validatePurchaseOrderLineOwnership(
        Organization $organization,
        PurchaseOrderLine $poLine,
        int $index,
    ): void {
        $inventoryItem = $poLine->inventoryItem;
        $purchaseUnit = $poLine->purchaseUnitOfMeasure;

        if (
            ! $inventoryItem instanceof InventoryItem
            || $inventoryItem->organization_id !== $organization->id
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.purchase_order_line_id" => __(
                    'The purchase-order line inventory item does not belong to the active organization.',
                ),
            ]);
        }

        if (
            ! $purchaseUnit instanceof UnitOfMeasure
            || $purchaseUnit->organization_id !== $organization->id
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.purchase_order_line_id" => __(
                    'The purchase-order line unit does not belong to the active organization.',
                ),
            ]);
        }
    }

    /**
     * Resolve an active storage destination only for accepted stock.
     */
    private function storageLocation(
        Organization $organization,
        PurchaseOrder $purchaseOrder,
        mixed $value,
        string $field,
    ): StorageLocation {
        $storageLocation = StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $purchaseOrder->location_id)
            ->where('active', true)
            ->find((int) $value);

        if ($storageLocation === null) {
            throw ValidationException::withMessages([
                $field => __(
                    'Select an active storage location belonging to the purchase-order location.',
                ),
            ]);
        }

        return $storageLocation;
    }

    /**
     * Resolve an active tenant-owned receiving UOM.
     */
    private function activeUnitOfMeasure(
        Organization $organization,
        mixed $value,
        string $field,
    ): UnitOfMeasure {
        $unit = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find((int) $value);

        if ($unit === null) {
            throw ValidationException::withMessages([
                $field => __('Select an active receiving unit.'),
            ]);
        }

        return $unit;
    }

    /**
     * Convert an entered receiving quantity to the PO line's base quantity.
     *
     * Purchase-unit receiving uses immutable PO snapshots rather than
     * mutable supplier-item configuration.
     */
    private function baseQuantity(
        Organization $organization,
        PurchaseOrderLine $poLine,
        BigDecimal $quantity,
        UnitOfMeasure $unit,
        string $field,
    ): BigDecimal {
        if ($unit->id === $poLine->purchase_unit_of_measure_id) {
            $basePerPurchaseUnit = BigDecimal::of(
                $poLine->base_quantity,
            )->dividedBy(
                BigDecimal::of($poLine->ordered_quantity),
                12,
                RoundingMode::HalfUp,
            );

            $baseQuantity = $quantity
                ->multipliedBy($basePerPurchaseUnit)
                ->toScale(6, RoundingMode::HalfUp);

            return $this->positiveBaseQuantity(
                $baseQuantity,
                $field,
            );
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
            (string) $quantity,
            $unit,
            $inventoryItem->baseUnitOfMeasure,
        );

        return $this->positiveBaseQuantity(
            BigDecimal::of($converted)->toScale(
                6,
                RoundingMode::HalfUp,
            ),
            $field,
        );
    }

    /**
     * Require a positive converted base snapshot within persisted precision.
     */
    private function positiveBaseQuantity(
        BigDecimal $quantity,
        string $field,
    ): BigDecimal {
        if (
            $quantity->compareTo(BigDecimal::zero()) <= 0
            || $quantity->isGreaterThan(
                BigDecimal::of(self::MAX_QUANTITY),
            )
        ) {
            throw ValidationException::withMessages([
                $field => __(
                    'The converted quantity must be greater than zero and within supported inventory precision.',
                ),
            ]);
        }

        return $quantity;
    }

    /**
     * Parse a non-negative fixed-precision receiving quantity.
     */
    private function nonNegativeQuantity(
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

        if (
            $quantity->compareTo(BigDecimal::zero()) < 0
            || $quantity->getScale() > 6
            || $quantity->isGreaterThan(
                BigDecimal::of(self::MAX_QUANTITY),
            )
        ) {
            throw ValidationException::withMessages([
                $field => __(
                    'Quantity must be non-negative with at most six decimal places.',
                ),
            ]);
        }

        return $quantity->toScale(6, RoundingMode::HalfUp);
    }

    /**
     * Determine whether a fixed-precision quantity is greater than zero.
     */
    private function isPositive(BigDecimal $quantity): bool
    {
        return $quantity->compareTo(BigDecimal::zero()) > 0;
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
