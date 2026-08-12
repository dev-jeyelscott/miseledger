<?php

namespace App\Actions\Inventory;

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
use Illuminate\Validation\ValidationException;

final class AdjustInventory
{
    public function __construct(
        private readonly ConvertQuantity $convertQuantity,
        private readonly RecordStockMovement $recordStockMovement,
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

        $baseQuantity = $this->convertQuantity->handle(
            $organization,
            $inventoryItem,
            $quantity,
            $unit,
            $inventoryItem->baseUnitOfMeasure,
        );

        return $this->recordStockMovement->handle(
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
    }
}
