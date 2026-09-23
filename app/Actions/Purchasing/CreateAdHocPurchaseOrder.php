<?php

namespace App\Actions\Purchasing;

use App\Actions\Inventory\ConvertQuantity;
use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create one synthetic, already-Approved purchase order for a delivery that
 * shows up with no matching desktop-planned PO, so mobile receiving can
 * reuse `SaveGoodsReceipt`/`FinalizeGoodsReceipt` completely unmodified
 * (Spec 3 §"Scope").
 */
final class CreateAdHocPurchaseOrder
{
    private const MAX_QUANTITY = '999999999.999999';

    public function __construct(
        private readonly ConvertQuantity $convertQuantity,
    ) {}

    /**
     * @param  list<array{inventory_item_id: int, unit_id: int, quantity: string}>  $items
     *
     * When `$existingPurchaseOrder` is given, no new purchase order is
     * created: the items are appended as new lines to that already-created
     * ad-hoc PO instead. A mobile receiving session may scan several
     * distinct items one at a time (Spec 3 §"Behavior and Flow" step 4);
     * the first scanned item creates the synthetic PO, and each
     * newly-encountered item after that extends the same one-supplier PO
     * rather than spawning a second PO for one delivery.
     */
    public function handle(
        Organization $organization,
        User $actor,
        Supplier $supplier,
        Location $location,
        array $items,
        ?PurchaseOrder $existingPurchaseOrder = null,
    ): PurchaseOrder {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $supplier,
            $location,
            $items,
            $existingPurchaseOrder,
        ): PurchaseOrder {
            $this->authorize($organization, $actor);

            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => __('At least one item is required to start an ad-hoc receipt.'),
                ]);
            }

            /*
             * Locking the supplier row for the whole transaction serializes
             * concurrent ad-hoc receipts for the same new supplier+item: the
             * second transaction blocks here until the first commits its new
             * SupplierItem, then finds and reuses it instead of racing to
             * create a duplicate (mirrors RecordWaste's locking discipline).
             */
            $lockedSupplier = Supplier::query()
                ->where('organization_id', $organization->id)
                ->where('active', true)
                ->whereKey($supplier->id)
                ->lockForUpdate()
                ->first();

            if ($lockedSupplier === null) {
                throw ValidationException::withMessages([
                    'supplier_id' => __('Select an active supplier.'),
                ]);
            }

            $lockedLocation = Location::query()
                ->where('organization_id', $organization->id)
                ->where('active', true)
                ->whereKey($location->id)
                ->lockForUpdate()
                ->first();

            if ($lockedLocation === null) {
                throw ValidationException::withMessages([
                    'location_id' => __('Select an active location.'),
                ]);
            }

            $purchaseOrder = null;

            if ($existingPurchaseOrder !== null) {
                $purchaseOrder = PurchaseOrder::query()
                    ->where('organization_id', $organization->id)
                    ->where('supplier_id', $lockedSupplier->id)
                    ->where('location_id', $lockedLocation->id)
                    ->where('origin', 'mobile_ad_hoc')
                    ->whereKey($existingPurchaseOrder->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $purchaseOrder === null
                    || ! $purchaseOrder->status->canReceive()
                ) {
                    throw ValidationException::withMessages([
                        'purchase_order' => __(
                            'This ad-hoc receiving session is no longer open.',
                        ),
                    ]);
                }
            }

            $lineSnapshots = [];
            $subtotal = BigDecimal::zero();

            foreach ($items as $index => $item) {
                $inventoryItem = $this->inventoryItem(
                    $organization,
                    $item['inventory_item_id'],
                    $index,
                );

                $unit = $this->unitOfMeasure(
                    $organization,
                    $item['unit_id'],
                    $index,
                );

                $quantity = $this->positiveQuantity(
                    $item['quantity'],
                    $index,
                );

                $supplierItem = $this->resolveOrCreateSupplierItem(
                    $organization,
                    $lockedSupplier,
                    $inventoryItem,
                    $unit,
                );

                $baseFactor = BigDecimal::of(
                    $this->convertQuantity->handle(
                        $organization,
                        $inventoryItem,
                        '1',
                        $unit,
                        $inventoryItem->baseUnitOfMeasure,
                    ),
                );

                $baseQuantity = $quantity
                    ->multipliedBy($baseFactor)
                    ->toScale(6, RoundingMode::HalfUp);

                if ($baseQuantity->compareTo(BigDecimal::zero()) <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => __(
                            'The converted quantity must be greater than zero.',
                        ),
                    ]);
                }

                $unitPrice = $this->unitPrice(
                    $supplierItem,
                    $baseFactor,
                );

                $lineTotal = $quantity
                    ->multipliedBy($unitPrice)
                    ->toScale(2, RoundingMode::HalfUp);

                $subtotal = $subtotal->plus($lineTotal);

                $lineSnapshots[] = [
                    'supplier_item_id' => $supplierItem->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'item_name_snapshot' => $inventoryItem->name,
                    'supplier_sku_snapshot' => $supplierItem->supplier_sku,
                    'ordered_quantity' => (string) $quantity,
                    'purchase_unit_of_measure_id' => $unit->id,
                    'base_quantity' => (string) $baseQuantity,
                    'unit_price' => (string) $unitPrice,
                    'line_total' => (string) $lineTotal,
                    'received_base_quantity' => '0.000000',
                ];
            }

            $subtotal = $subtotal->toScale(2, RoundingMode::HalfUp);

            if ($purchaseOrder === null) {
                $purchaseOrder = PurchaseOrder::query()->create([
                    'organization_id' => $organization->id,
                    'location_id' => $lockedLocation->id,
                    'supplier_id' => $lockedSupplier->id,
                    'number' => $this->uniqueNumber($organization),
                    'status' => PurchaseOrderStatus::Approved,
                    'origin' => 'mobile_ad_hoc',
                    'order_date' => now()->toDateString(),
                    'expected_delivery_date' => null,
                    'subtotal' => (string) $subtotal,
                    'tax_total' => '0.00',
                    'discount_total' => '0.00',
                    'total' => (string) $subtotal,
                    'notes' => null,
                    'created_by' => $actor->id,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                ]);
            } else {
                $newSubtotal = BigDecimal::of($purchaseOrder->subtotal)
                    ->plus($subtotal)
                    ->toScale(2, RoundingMode::HalfUp);

                $purchaseOrder->forceFill([
                    'subtotal' => (string) $newSubtotal,
                    'total' => (string) $newSubtotal
                        ->minus(BigDecimal::of($purchaseOrder->discount_total))
                        ->plus(BigDecimal::of($purchaseOrder->tax_total)),
                ])->save();
            }

            $purchaseOrder->lines()->createMany($lineSnapshots);

            return $purchaseOrder->refresh();
        }, 3);
    }

    /**
     * Require receiving permission, matching the mobile controller's gate.
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
     * Resolve an active tenant-owned inventory item.
     */
    private function inventoryItem(
        Organization $organization,
        int $inventoryItemId,
        int $index,
    ): InventoryItem {
        $inventoryItem = InventoryItem::query()
            ->with('baseUnitOfMeasure')
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find($inventoryItemId);

        if ($inventoryItem === null) {
            throw ValidationException::withMessages([
                "items.{$index}.inventory_item_id" => __(
                    'Select an active inventory item from the current organization.',
                ),
            ]);
        }

        return $inventoryItem;
    }

    /**
     * Resolve an active tenant-owned receiving unit.
     */
    private function unitOfMeasure(
        Organization $organization,
        int $unitId,
        int $index,
    ): UnitOfMeasure {
        $unit = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find($unitId);

        if ($unit === null) {
            throw ValidationException::withMessages([
                "items.{$index}.unit_id" => __('Select an active receiving unit.'),
            ]);
        }

        return $unit;
    }

    /**
     * Reuse an existing supplier/item mapping, or create one on the fly.
     *
     * The supplier row is already locked for the whole transaction, so this
     * lookup-then-create is race-free: a concurrent request for the same
     * new supplier+item blocks on the supplier lock until this transaction
     * commits, then finds the row created here instead of duplicating it.
     */
    private function resolveOrCreateSupplierItem(
        Organization $organization,
        Supplier $supplier,
        InventoryItem $inventoryItem,
        UnitOfMeasure $unit,
    ): SupplierItem {
        $existing = SupplierItem::query()
            ->where('organization_id', $organization->id)
            ->where('supplier_id', $supplier->id)
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('active', true)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $baseFactor = $this->convertQuantity->handle(
            $organization,
            $inventoryItem,
            '1',
            $unit,
            $inventoryItem->baseUnitOfMeasure,
        );

        return SupplierItem::query()->create([
            'organization_id' => $organization->id,
            'supplier_id' => $supplier->id,
            'inventory_item_id' => $inventoryItem->id,
            'supplier_sku' => sprintf(
                'MOBILE-AUTO-%d-%s',
                $inventoryItem->id,
                Str::upper(Str::random(6)),
            ),
            'description' => null,
            'purchase_unit_of_measure_id' => $unit->id,
            'base_quantity' => $baseFactor,
            'current_price' => null,
            'currency' => $organization->currency,
            'active' => true,
        ]);
    }

    /**
     * Derive the PO line's unit price in terms of the entered receiving
     * unit, converting from the supplier item's own price-bearing unit so a
     * reused supplier item on a different purchase unit still prices
     * correctly. A supplier item with no price on file (new item, or
     * reused item never priced) yields an explicit zero, surfaced by the
     * review screen's non-dismissible warning rather than a guess.
     */
    private function unitPrice(
        SupplierItem $supplierItem,
        BigDecimal $baseFactorForReceivingUnit,
    ): BigDecimal {
        if ($supplierItem->current_price === null) {
            return BigDecimal::zero()->toScale(4);
        }

        $pricePerBaseUnit = BigDecimal::of($supplierItem->current_price)
            ->dividedBy(
                BigDecimal::of($supplierItem->base_quantity),
                10,
                RoundingMode::HalfUp,
            );

        return $pricePerBaseUnit
            ->multipliedBy($baseFactorForReceivingUnit)
            ->toScale(4, RoundingMode::HalfUp);
    }

    /**
     * Parse and require a positive fixed-precision quantity.
     */
    private function positiveQuantity(
        mixed $value,
        int $index,
    ): BigDecimal {
        try {
            $quantity = BigDecimal::of((string) $value)
                ->toScale(6, RoundingMode::HalfUp);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" => __('A valid quantity is required.'),
            ]);
        }

        if (
            $quantity->compareTo(BigDecimal::zero()) <= 0
            || $quantity->isGreaterThan(BigDecimal::of(self::MAX_QUANTITY))
        ) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" => __(
                    'Quantity must be greater than zero and within the supported range.',
                ),
            ]);
        }

        return $quantity;
    }

    /**
     * Generate an organization-unique ad-hoc PO number.
     */
    private function uniqueNumber(Organization $organization): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = sprintf(
                'PO-AH-%s-%s',
                now()->format('ymdHis'),
                Str::upper(Str::random(4)),
            );

            $exists = PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->where('number', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'number' => __('Unable to generate a unique purchase-order number. Try again.'),
        ]);
    }
}
