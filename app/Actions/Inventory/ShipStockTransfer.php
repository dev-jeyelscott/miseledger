<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrganizationPermission;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ShipStockTransfer
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /**
     * Atomically remove shipped stock and snapshot source cost.
     */
    public function handle(
        Organization $organization,
        User $actor,
        StockTransfer $stockTransfer,
    ): StockTransfer {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $stockTransfer,
        ): StockTransfer {
            $this->authorize(
                $organization,
                $actor,
            );

            $transfer = StockTransfer::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->whereKey($stockTransfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $transfer->status
                === StockTransferStatus::Shipped
            ) {
                return $transfer->refresh();
            }

            if (
                $transfer->status
                !== StockTransferStatus::Draft
            ) {
                throw ValidationException::withMessages([
                    'stock_transfer' => __(
                        'Only draft stock transfers can be shipped.',
                    ),
                ]);
            }

            $location = Location::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->whereKey($transfer->from_location_id)
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if ($location === null) {
                throw ValidationException::withMessages([
                    'from_location_id' => __(
                        'The transfer source location is no longer active.',
                    ),
                ]);
            }

            $storageLocation =
                StorageLocation::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where(
                        'location_id',
                        $location->id,
                    )
                    ->whereKey(
                        $transfer
                            ->from_storage_location_id,
                    )
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

            if ($storageLocation === null) {
                throw ValidationException::withMessages([
                    'from_storage_location_id' => __(
                        'The transfer source storage location is no longer active.',
                    ),
                ]);
            }

            /*
             * The locked transfer header serializes these checks against the
             * deactivation guard without locking destination stock records.
             */
            $destinationLocationIsActive = Location::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->whereKey($transfer->to_location_id)
                ->where('active', true)
                ->exists();

            if (! $destinationLocationIsActive) {
                throw ValidationException::withMessages([
                    'to_location_id' => __(
                        'The transfer destination location is no longer active.',
                    ),
                ]);
            }

            $destinationStorageIsActive =
                StorageLocation::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where(
                        'location_id',
                        $transfer->to_location_id,
                    )
                    ->whereKey(
                        $transfer
                            ->to_storage_location_id,
                    )
                    ->where('active', true)
                    ->exists();

            if (! $destinationStorageIsActive) {
                throw ValidationException::withMessages([
                    'to_storage_location_id' => __(
                        'The transfer destination storage location is no longer active.',
                    ),
                ]);
            }

            $lines = StockTransferLine::query()
                ->where(
                    'stock_transfer_id',
                    $transfer->id,
                )
                ->orderBy('inventory_item_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'At least one stock-transfer line is required.',
                    ),
                ]);
            }

            $shippedAt = now();
            $movementCount = 0;

            foreach ($lines as $line) {
                $inventoryItem = InventoryItem::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey(
                        $line->inventory_item_id,
                    )
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if ($inventoryItem === null) {
                    throw ValidationException::withMessages([
                        'stock_transfer' => __(
                            'One or more transferred inventory items are no longer active.',
                        ),
                    ]);
                }

                $baseUnit = UnitOfMeasure::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey(
                        $inventoryItem
                            ->base_unit_of_measure_id,
                    )
                    ->where('active', true)
                    ->first();

                if ($baseUnit === null) {
                    throw ValidationException::withMessages([
                        'stock_transfer' => __(
                            'One or more transferred inventory items do not have an active base unit.',
                        ),
                    ]);
                }

                $movement =
                    $this->recordStockMovement->handle(
                        organization: $organization,
                        location: $location,
                        storageLocation: $storageLocation,
                        inventoryItem: $inventoryItem,
                        type: StockMovementType::TransferOut,
                        baseQuantity: '-'.$line->requested_base_quantity,
                        baseUnitOfMeasure: $baseUnit,
                        referenceType: 'stock_transfer_line',
                        referenceId: $line->id,
                        occurredAt: $shippedAt,
                        actor: $actor,
                        idempotencyKey: "stock_transfer:{$transfer->id}:line:{$line->id}:out",
                        notes: __(
                            'Stock transfer :number shipment',
                            [
                                'number' => $transfer->number,
                            ],
                        ),
                    );

                $line->forceFill([
                    'shipped_base_quantity' => $line->requested_base_quantity,
                    'unit_cost' => $movement->unit_cost,
                ])->save();

                $movementCount++;
            }

            $transfer->forceFill([
                'status' => StockTransferStatus::Shipped,
                'shipped_at' => $shippedAt,
                'shipped_by' => $actor->id,
            ])->save();

            $this->recordAuditEntry->handle(
                organization: $organization,
                actor: $actor,
                action: 'stock_transfer.shipped',
                entityType: 'stock_transfer',
                entityId: $transfer->id,
                beforeData: [
                    'status' => StockTransferStatus::Draft->value,
                ],
                afterData: [
                    'status' => StockTransferStatus::Shipped->value,
                    'shipped_at' => $shippedAt->toIso8601String(),
                    'line_count' => $lines->count(),
                    'movement_count' => $movementCount,
                ],
                correlationId: "stock-transfer:{$transfer->id}:ship",
            );

            return $transfer->refresh();
        }, 3);
    }

    /**
     * Require explicit transfer shipment permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::TransfersShip,
            )
        ) {
            abort(403);
        }
    }
}
