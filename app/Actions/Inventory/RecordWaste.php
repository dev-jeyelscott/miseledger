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
use App\Models\WasteReason;
use App\Models\WasteRecord;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordWaste
{
    private const MAX_QUANTITY = '999999999.999999';

    public function __construct(
        private readonly ConvertQuantity $convertQuantity,
        private readonly RecordStockMovement $recordStockMovement,
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /**
     * Atomically retain finalized waste evidence and its negative ledger movement.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(
        Organization $organization,
        User $actor,
        array $data,
    ): WasteRecord {
        $this->authorize($organization, $actor);

        $operationId = (string) $data['operation_id'];
        $quantity = $this->quantity(
            (string) $data['quantity'],
        );
        $notes = $this->notes($data['notes'] ?? null);

        $occurredAt = CarbonImmutable::parse(
            (string) $data['occurred_at'],
            $organization->timezone,
        )->utc();

        try {
            return DB::transaction(function () use (
                $organization,
                $actor,
                $data,
                $operationId,
                $quantity,
                $notes,
                $occurredAt,
            ): WasteRecord {
                $existing = WasteRecord::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where(
                        'operation_id',
                        $operationId,
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $this->existingRecord(
                        $organization,
                        $actor,
                        $existing,
                        $data,
                        $quantity,
                        $notes,
                        $occurredAt,
                    );
                }

                $location = Location::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey(
                        (int) $data['location_id'],
                    )
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if ($location === null) {
                    throw ValidationException::withMessages([
                        'location_id' => __(
                            'Select an active location from the current organization.',
                        ),
                    ]);
                }

                $storageLocation = StorageLocation::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->where(
                        'location_id',
                        $location->id,
                    )
                    ->whereKey(
                        (int) $data['storage_location_id'],
                    )
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if ($storageLocation === null) {
                    throw ValidationException::withMessages([
                        'storage_location_id' => __(
                            'Select an active storage location from the selected location.',
                        ),
                    ]);
                }

                $inventoryItem = InventoryItem::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey(
                        (int) $data['inventory_item_id'],
                    )
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if ($inventoryItem === null) {
                    throw ValidationException::withMessages([
                        'inventory_item_id' => __(
                            'Select an active inventory item from the current organization.',
                        ),
                    ]);
                }

                $reason = WasteReason::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey(
                        (int) $data['waste_reason_id'],
                    )
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if ($reason === null) {
                    throw ValidationException::withMessages([
                        'waste_reason_id' => __(
                            'Select an active waste reason from the current organization.',
                        ),
                    ]);
                }

                $unit = UnitOfMeasure::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey(
                        (int) $data['unit_id'],
                    )
                    ->where('active', true)
                    ->first();

                if ($unit === null) {
                    throw ValidationException::withMessages([
                        'unit_id' => __(
                            'Select an active unit from the current organization.',
                        ),
                    ]);
                }

                $baseUnit = UnitOfMeasure::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey(
                        $inventoryItem->base_unit_of_measure_id,
                    )
                    ->where('active', true)
                    ->first();

                if ($baseUnit === null) {
                    throw ValidationException::withMessages([
                        'inventory_item_id' => __(
                            'The inventory item does not have an active base unit.',
                        ),
                    ]);
                }

                $baseQuantity = $this->convertQuantity->handle(
                    $organization,
                    $inventoryItem,
                    $quantity,
                    $unit,
                    $baseUnit,
                );

                $baseQuantityDecimal = BigDecimal::of(
                    $baseQuantity,
                )->toScale(
                    6,
                    RoundingMode::HalfUp,
                );

                if (
                    $baseQuantityDecimal->compareTo(
                        BigDecimal::zero(),
                    ) <= 0
                ) {
                    throw ValidationException::withMessages([
                        'quantity' => __(
                            'Waste quantity must convert to a positive base quantity.',
                        ),
                    ]);
                }

                $record = WasteRecord::query()->create([
                    'organization_id' => $organization->id,
                    'location_id' => $location->id,
                    'storage_location_id' => $storageLocation->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'waste_reason_id' => $reason->id,
                    'operation_id' => $operationId,
                    'quantity' => $quantity,
                    'unit_id' => $unit->id,
                    'base_quantity' => (string) $baseQuantityDecimal,
                    'unit_cost' => '0.0000',
                    'total_cost' => '0.0000',
                    'occurred_at' => $occurredAt,
                    'recorded_by' => $actor->id,
                    'notes' => $notes,
                ]);

                $movement = $this->recordStockMovement->handle(
                    organization: $organization,
                    location: $location,
                    storageLocation: $storageLocation,
                    inventoryItem: $inventoryItem,
                    type: StockMovementType::Waste,
                    baseQuantity: (string) $baseQuantityDecimal
                        ->negated(),
                    baseUnitOfMeasure: $baseUnit,
                    referenceType: 'waste_record',
                    referenceId: $record->id,
                    occurredAt: $occurredAt,
                    actor: $actor,
                    idempotencyKey: "waste:{$record->id}",
                    notes: __(
                        'Waste: :reason',
                        ['reason' => $reason->name],
                    ),
                );

                /*
                 * RecordStockMovement has already snapshotted the current
                 * average cost. Persist exactly the same immutable evidence.
                 */
                $record->forceFill([
                    'unit_cost' => $movement->unit_cost
                        ?? '0.0000',
                    'total_cost' => $movement->total_cost
                        ?? '0.0000',
                ])->save();

                $this->recordAuditEntry->handle(
                    organization: $organization,
                    actor: $actor,
                    action: 'waste.recorded',
                    entityType: 'waste_record',
                    entityId: $record->id,
                    beforeData: null,
                    afterData: [
                        'location_id' => $location->id,
                        'storage_location_id' => $storageLocation->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'waste_reason_id' => $reason->id,
                        'quantity' => $quantity,
                        'unit_id' => $unit->id,
                        'base_quantity' => (string) $baseQuantityDecimal,
                        'unit_cost' => $movement->unit_cost,
                        'total_cost' => $movement->total_cost,
                        'movement_id' => $movement->id,
                        'occurred_at' => $occurredAt
                            ->toIso8601String(),
                    ],
                    correlationId: "waste:{$record->id}",
                );

                return $record->refresh();
            }, 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            /*
             * A concurrent request with the same operation UUID may win the
             * unique constraint while this request waits. Resolve the already
             * committed operation instead of creating duplicate waste.
             */
            $existing = WasteRecord::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->where(
                    'operation_id',
                    $operationId,
                )
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->existingRecord(
                $organization,
                $actor,
                $existing,
                $data,
                $quantity,
                $notes,
                $occurredAt,
            );
        }
    }

    /**
     * Validate that a retry represents exactly the already-recorded operation.
     *
     * @param  array<string, mixed>  $data
     */
    private function existingRecord(
        Organization $organization,
        User $actor,
        WasteRecord $record,
        array $data,
        string $quantity,
        ?string $notes,
        CarbonImmutable $occurredAt,
    ): WasteRecord {
        $sameOperation =
            $record->organization_id === $organization->id
            && $record->location_id === (int) $data['location_id']
            && $record->storage_location_id
                === (int) $data['storage_location_id']
            && $record->inventory_item_id
                === (int) $data['inventory_item_id']
            && $record->waste_reason_id
                === (int) $data['waste_reason_id']
            && $record->quantity === $quantity
            && $record->unit_id === (int) $data['unit_id']
            && $record->recorded_by === $actor->id
            && $record->notes === $notes
            && $record->occurred_at->equalTo($occurredAt);

        if (! $sameOperation) {
            throw ValidationException::withMessages([
                'operation_id' => __(
                    'This waste operation identifier has already been used for different waste evidence.',
                ),
            ]);
        }

        $movementExists = StockMovement::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where(
                'type',
                StockMovementType::Waste->value,
            )
            ->where(
                'reference_type',
                'waste_record',
            )
            ->where(
                'reference_id',
                $record->id,
            )
            ->where(
                'idempotency_key',
                "waste:{$record->id}",
            )
            ->exists();

        if (! $movementExists) {
            throw ValidationException::withMessages([
                'operation_id' => __(
                    'The existing waste operation is missing its authoritative stock movement.',
                ),
            ]);
        }

        return $record->refresh();
    }

    /**
     * Normalize positive entered quantity to inventory precision.
     */
    private function quantity(string $value): string
    {
        try {
            $quantity = BigDecimal::of(
                trim($value),
            )->toScale(
                6,
                RoundingMode::HalfUp,
            );
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'Waste quantity must be a valid decimal number.',
                ),
            ]);
        }

        if (
            $quantity->compareTo(BigDecimal::zero()) <= 0
            || $quantity->compareTo(
                BigDecimal::of(self::MAX_QUANTITY),
            ) > 0
        ) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'Waste quantity must be greater than zero and within the supported range.',
                ),
            ]);
        }

        return (string) $quantity;
    }

    /**
     * Normalize optional immutable waste notes.
     */
    private function notes(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $notes = trim($value);

        return $notes === ''
            ? null
            : $notes;
    }

    /**
     * Require explicit waste-recording permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::WasteRecord,
            )
        ) {
            abort(403);
        }
    }
}
