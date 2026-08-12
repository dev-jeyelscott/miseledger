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
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class RecordOpeningBalance
{
    public function __construct(
        private readonly ConvertQuantity $convertQuantity,
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    /**
     * Convert initial stock to the item base UOM and record an auditable opening movement.
     */
    public function handle(
        Organization $organization,
        Location $location,
        StorageLocation $storageLocation,
        InventoryItem $inventoryItem,
        string $quantity,
        UnitOfMeasure $unit,
        string $baseUnitCost,
        string $referenceType,
        int $referenceId,
        CarbonInterface $occurredAt,
        string $idempotencyKey,
        User $actor,
        ?string $notes = null,
    ): StockMovement {
        if (! $actor->hasOrganizationPermission(
            $organization,
            OrganizationPermission::InventoryAdjust,
        )) {
            throw new AuthorizationException(
                'You are not authorized to record opening inventory.',
            );
        }

        if (! $unit->active) {
            throw ValidationException::withMessages([
                'unit' => __(
                    'Select an active unit of measure.',
                ),
            ]);
        }

        try {
            $enteredQuantity = BigDecimal::of(
                trim($quantity),
            );
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'A valid opening quantity is required.',
                ),
            ]);
        }

        if (
            $enteredQuantity->compareTo(
                BigDecimal::zero(),
            ) <= 0
        ) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'Opening quantity must be greater than zero.',
                ),
            ]);
        }

        if (trim($idempotencyKey) === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => __(
                    'An opening-balance idempotency key is required.',
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
            type: StockMovementType::OpeningBalance,
            baseQuantity: $baseQuantity,
            baseUnitOfMeasure: $inventoryItem->baseUnitOfMeasure,
            referenceType: $referenceType,
            referenceId: $referenceId,
            occurredAt: $occurredAt,
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            notes: $notes,
            inboundUnitCost: $baseUnitCost,
        );
    }
}
