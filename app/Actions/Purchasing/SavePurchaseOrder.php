<?php

namespace App\Actions\Purchasing;

use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SavePurchaseOrder
{
    /**
     * Create or replace one draft purchase order transactionally.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Organization $organization,
        User $actor,
        array $attributes,
        ?PurchaseOrder $purchaseOrder = null,
    ): PurchaseOrder {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $attributes,
            $purchaseOrder,
        ): PurchaseOrder {
            $this->authorize($organization, $actor);

            $record = $purchaseOrder === null
                ? null
                : PurchaseOrder::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($purchaseOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $record !== null
                && ! $record->status->isEditable()
            ) {
                throw ValidationException::withMessages([
                    'purchase_order' => __(
                        'Only draft purchase orders can be edited.',
                    ),
                ]);
            }

            $supplier = $this->supplier(
                $organization,
                (int) $attributes['supplier_id'],
            );

            $location = $this->location(
                $organization,
                (int) $attributes['location_id'],
            );

            $rawLines = $attributes['lines'] ?? [];

            if (! is_array($rawLines) || $rawLines === []) {
                throw ValidationException::withMessages([
                    'lines' => __('At least one purchase line is required.'),
                ]);
            }

            $lineSnapshots = [];
            $seenSupplierItems = [];
            $subtotal = BigDecimal::zero();

            foreach (array_values($rawLines) as $index => $rawLine) {
                if (! is_array($rawLine)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}" => __('Invalid purchase line.'),
                    ]);
                }

                $supplierItemId = (int) (
                    $rawLine['supplier_item_id'] ?? 0
                );

                if (isset($seenSupplierItems[$supplierItemId])) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.supplier_item_id" => __(
                            'The same supplier item cannot appear twice on one purchase order.',
                        ),
                    ]);
                }

                $seenSupplierItems[$supplierItemId] = true;

                $supplierItem = $this->supplierItem(
                    $organization,
                    $supplier,
                    $supplierItemId,
                    $index,
                );

                if ($supplierItem->current_price === null) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.supplier_item_id" => __(
                            'A current supplier price is required before ordering this item.',
                        ),
                    ]);
                }

                if ($supplierItem->currency !== $organization->currency) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.supplier_item_id" => __(
                            'The supplier item currency must match the organization currency.',
                        ),
                    ]);
                }

                $orderedQuantity = $this->positiveQuantity(
                    $rawLine['ordered_quantity'] ?? null,
                    "lines.{$index}.ordered_quantity",
                );

                $basePerPurchaseUnit = BigDecimal::of(
                    $supplierItem->base_quantity,
                );

                $baseQuantity = $orderedQuantity
                    ->multipliedBy($basePerPurchaseUnit)
                    ->toScale(6, RoundingMode::HalfUp);

                $unitPrice = BigDecimal::of(
                    $supplierItem->current_price,
                )->toScale(4, RoundingMode::HalfUp);

                $lineTotal = $orderedQuantity
                    ->multipliedBy($unitPrice)
                    ->toScale(2, RoundingMode::HalfUp);

                $subtotal = $subtotal->plus($lineTotal);

                $lineSnapshots[] = [
                    'supplier_item_id' => $supplierItem->id,
                    'inventory_item_id' => $supplierItem->inventory_item_id,
                    'item_name_snapshot' => $supplierItem->inventoryItem->name,
                    'supplier_sku_snapshot' => $supplierItem->supplier_sku,
                    'ordered_quantity' => (string) $orderedQuantity
                        ->toScale(6, RoundingMode::HalfUp),
                    'purchase_unit_of_measure_id' => $supplierItem
                        ->purchase_unit_of_measure_id,
                    'base_quantity' => (string) $baseQuantity,
                    'unit_price' => (string) $unitPrice,
                    'line_total' => (string) $lineTotal,
                    'received_base_quantity' => '0.000000',
                ];
            }

            $subtotal = $subtotal->toScale(
                2,
                RoundingMode::HalfUp,
            );

            $values = [
                'organization_id' => $organization->id,
                'location_id' => $location->id,
                'supplier_id' => $supplier->id,
                'number' => (string) $attributes['number'],
                'order_date' => (string) $attributes['order_date'],
                'expected_delivery_date' => $attributes[
                    'expected_delivery_date'
                ] ?? null,
                'subtotal' => (string) $subtotal,
                'tax_total' => '0.00',
                'discount_total' => '0.00',
                'total' => (string) $subtotal,
                'notes' => $attributes['notes'] ?? null,
            ];

            if ($record === null) {
                $record = PurchaseOrder::query()->create([
                    ...$values,
                    'status' => PurchaseOrderStatus::Draft,
                    'created_by' => $actor->id,
                ]);
            } else {
                $record->fill($values);
                $record->save();

                $record->lines()->delete();
            }

            $record->lines()->createMany($lineSnapshots);

            return $record->refresh();
        }, 3);
    }

    /**
     * Require purchasing management permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::PurchasingManage,
            )
        ) {
            abort(403);
        }
    }

    /**
     * Resolve one active tenant-owned supplier.
     */
    private function supplier(
        Organization $organization,
        int $supplierId,
    ): Supplier {
        $supplier = Supplier::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find($supplierId);

        if ($supplier === null) {
            throw ValidationException::withMessages([
                'supplier_id' => __('Select an active supplier.'),
            ]);
        }

        return $supplier;
    }

    /**
     * Resolve one active tenant-owned destination location.
     */
    private function location(
        Organization $organization,
        int $locationId,
    ): Location {
        $location = Location::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find($locationId);

        if ($location === null) {
            throw ValidationException::withMessages([
                'location_id' => __('Select an active location.'),
            ]);
        }

        return $location;
    }

    /**
     * Resolve an active supplier purchase-pack mapping.
     */
    private function supplierItem(
        Organization $organization,
        Supplier $supplier,
        int $supplierItemId,
        int $index,
    ): SupplierItem {
        $supplierItem = SupplierItem::query()
            ->with([
                'inventoryItem',
                'purchaseUnitOfMeasure',
            ])
            ->where('organization_id', $organization->id)
            ->where('supplier_id', $supplier->id)
            ->where('active', true)
            ->find($supplierItemId);

        if (
            $supplierItem === null
            || ! $supplierItem->inventoryItem->active
            || ! $supplierItem->purchaseUnitOfMeasure->active
        ) {
            throw ValidationException::withMessages([
                "lines.{$index}.supplier_item_id" => __(
                    'Select an active supplier item.',
                ),
            ]);
        }

        return $supplierItem;
    }

    /**
     * Parse and require a positive fixed-precision quantity.
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
}
