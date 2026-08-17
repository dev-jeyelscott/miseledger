<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrganizationPermission;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdjustInventory
{
    public function __construct(
        private readonly ConvertQuantity $convertQuantity,
        private readonly RecordStockMovement $recordStockMovement,
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /**
     * Record one privileged manual correction through the shared stock ledger boundary.
     */
    public function handle(
        Organization $organization,
        Location $location,
        StorageLocation $storageLocation,
        InventoryItem $inventoryItem,
        string $quantity,
        UnitOfMeasure $unit,
        string $reason,
        string $referenceType,
        int $referenceId,
        CarbonInterface $occurredAt,
        User $actor,
        string $idempotencyKey,
    ): StockMovement {
        if (! $actor->hasOrganizationPermission(
            $organization,
            OrganizationPermission::InventoryAdjust,
        )) {
            throw new AuthorizationException(
                'You are not authorized to adjust inventory.',
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __(
                    'A reason is required for manual inventory adjustment.',
                ),
            ]);
        }

        if (trim($idempotencyKey) === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => __(
                    'An adjustment idempotency key is required.',
                ),
            ]);
        }

        if (! $unit->active) {
            throw ValidationException::withMessages([
                'unit' => __(
                    'Select an active unit of measure.',
                ),
            ]);
        }

        $idempotencyKey = trim($idempotencyKey);

        return DB::transaction(function () use (
            $organization,
            $location,
            $storageLocation,
            $inventoryItem,
            $quantity,
            $unit,
            $reason,
            $referenceType,
            $referenceId,
            $occurredAt,
            $actor,
            $idempotencyKey,
        ): StockMovement {
            $alreadyRecorded = StockMovement::query()
                ->where(
                    'organization_id',
                    $organization->getKey(),
                )
                ->where(
                    'idempotency_key',
                    $idempotencyKey,
                )
                ->exists();

            $baseQuantity = $this->convertQuantity->handle(
                $organization,
                $inventoryItem,
                $quantity,
                $unit,
                $inventoryItem->baseUnitOfMeasure,
            );

            $movement = $this->recordStockMovement->handle(
                organization: $organization,
                location: $location,
                storageLocation: $storageLocation,
                inventoryItem: $inventoryItem,
                type: StockMovementType::ManualAdjustment,
                baseQuantity: $baseQuantity,
                baseUnitOfMeasure: $inventoryItem->baseUnitOfMeasure,
                referenceType: $referenceType,
                referenceId: $referenceId,
                occurredAt: $occurredAt,
                actor: $actor,
                idempotencyKey: $idempotencyKey,
                notes: $reason,
            );

            if (! $alreadyRecorded) {
                $this->recordAuditEntry->handle(
                    organization: $organization,
                    actor: $actor,
                    action: 'inventory.manual_adjustment',
                    entityType: 'stock_movement',
                    entityId: $movement->id,
                    beforeData: null,
                    afterData: [
                        'location_id' => $location->getKey(),
                        'storage_location_id' => $storageLocation->getKey(),
                        'inventory_item_id' => $inventoryItem->getKey(),
                        'quantity' => $quantity,
                        'unit_id' => $unit->getKey(),
                        'base_quantity' => $movement->quantity,
                        'reason' => $reason,
                        'occurred_at' => $occurredAt
                            ->toIso8601String(),
                    ],
                    correlationId: "inventory_adjustment:{$idempotencyKey}",
                );
            }

            return $movement;
        }, 3);
    }
}
