<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordStockMovement
{
    private const QUANTITY_SCALE = 6;

    private const MONEY_SCALE = 4;

    private const MAX_QUANTITY = '999999999.999999';

    private const MIN_QUANTITY = '-999999999.999999';

    private const MAX_MONEY = '99999999999.9999';

    private const MIN_MONEY = '-99999999999.9999';

    /**
     * Atomically append one base-unit movement and update its balance projection.
     */
    public function handle(
        Organization $organization,
        Location $location,
        StorageLocation $storageLocation,
        InventoryItem $inventoryItem,
        StockMovementType $type,
        string $baseQuantity,
        UnitOfMeasure $baseUnitOfMeasure,
        string $referenceType,
        int $referenceId,
        CarbonInterface $occurredAt,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $notes = null,
        ?string $inboundUnitCost = null,
        ?bool &$wasCreated = null,
        ?LockedDependencies $lockedDependencies = null,
    ): StockMovement {
        if ($wasCreated !== null) {
            $wasCreated = false;
        }

        $quantity = $this->quantity($baseQuantity);
        $referenceType = trim($referenceType);
        $idempotencyKey = $this->normalizeIdempotencyKey(
            $idempotencyKey,
        );
        $notes = $this->normalizeNotes($notes);
        $explicitInboundCost = $inboundUnitCost === null
            ? null
            : $this->nonNegativeMoney(
                $inboundUnitCost,
                'unit_cost',
            );

        $this->validateReference($referenceType, $referenceId);
        $this->validateDirection($type, $quantity);
        $this->validateCostContract(
            $type,
            $explicitInboundCost,
        );

        try {
            $operation = function () use (
                $organization,
                $location,
                $storageLocation,
                $inventoryItem,
                $type,
                $quantity,
                $baseUnitOfMeasure,
                $referenceType,
                $referenceId,
                $occurredAt,
                $actor,
                $idempotencyKey,
                $notes,
                $explicitInboundCost,
                &$wasCreated,
                $lockedDependencies,
            ): StockMovement {
                if (
                    $actor !== null
                    && ($lockedDependencies === null
                        || ! $lockedDependencies->actorMembershipValidated)
                    && $actor->organizationMemberships()
                        ->where(
                            'organization_id',
                            $organization->getKey(),
                        )
                        ->doesntExist()
                ) {
                    throw ValidationException::withMessages([
                        'created_by' => __(
                            'The movement actor does not belong to the active organization.',
                        ),
                    ]);
                }

                $existing = $lockedDependencies !== null && $idempotencyKey !== null
                    ? $lockedDependencies->idempotentMovement($idempotencyKey)
                    : $this->existingIdempotentMovement(
                        $organization,
                        $idempotencyKey,
                    );

                if ($existing !== null) {
                    $this->assertIdempotentMatch(
                        $existing,
                        $location,
                        $storageLocation,
                        $inventoryItem,
                        $baseUnitOfMeasure,
                        $type,
                        $quantity,
                        $referenceType,
                        $referenceId,
                        $explicitInboundCost,
                        $notes,
                    );

                    return $existing;
                }

                $activeLocation = $lockedDependencies?->location;

                if ($activeLocation === null) {
                    $activeLocation = Location::query()
                        ->where(
                            'organization_id',
                            $organization->getKey(),
                        )
                        ->whereKey($location->getKey())
                        ->where('active', true)
                        ->lockForUpdate()
                        ->first();
                }

                if ($activeLocation === null) {
                    throw ValidationException::withMessages([
                        'location' => __(
                            'Select an active location from the current organization.',
                        ),
                    ]);
                }

                $activeStorage = $lockedDependencies?->storageLocation(
                    $storageLocation->id,
                );

                if ($activeStorage === null) {
                    $activeStorage = StorageLocation::query()
                        ->where(
                            'organization_id',
                            $organization->getKey(),
                        )
                        ->where(
                            'location_id',
                            $activeLocation->getKey(),
                        )
                        ->whereKey($storageLocation->getKey())
                        ->where('active', true)
                        ->lockForUpdate()
                        ->first();
                }

                if ($activeStorage === null) {
                    throw ValidationException::withMessages([
                        'storage_location' => __(
                            'The storage location does not belong to the selected active location.',
                        ),
                    ]);
                }

                $activeItem = $lockedDependencies?->inventoryItem(
                    $inventoryItem->id,
                );

                if ($activeItem === null) {
                    $activeItem = InventoryItem::query()
                        ->where(
                            'organization_id',
                            $organization->getKey(),
                        )
                        ->whereKey($inventoryItem->getKey())
                        ->where('active', true)
                        ->lockForUpdate()
                        ->first();
                }

                if ($activeItem === null) {
                    throw ValidationException::withMessages([
                        'inventory_item' => __(
                            'Select an active inventory item from the current organization.',
                        ),
                    ]);
                }

                $baseUnit = $lockedDependencies?->baseUnit(
                    $activeItem->base_unit_of_measure_id,
                );

                if ($baseUnit === null) {
                    $baseUnit = UnitOfMeasure::query()
                        ->where(
                            'organization_id',
                            $organization->getKey(),
                        )
                        ->whereKey(
                            $activeItem->base_unit_of_measure_id,
                        )
                        ->where('active', true)
                        ->first();
                }

                if ($baseUnit === null) {
                    throw ValidationException::withMessages([
                        'base_unit_of_measure_id' => __(
                            'The inventory item must have an active base unit before stock can move.',
                        ),
                    ]);
                }

                if (
                    $baseUnitOfMeasure->getKey()
                    !== $baseUnit->getKey()
                ) {
                    throw ValidationException::withMessages([
                        'base_unit_of_measure_id' => __(
                            'The movement quantity must use the inventory item\'s current base unit.',
                        ),
                    ]);
                }

                if ($lockedDependencies === null && ! Organization::query()
                    ->whereKey($organization->getKey())
                    ->where('active', true)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'organization' => __(
                            'The active organization is disabled.',
                        ),
                    ]);
                }

                $balanceKey = $this->balanceKey(
                    $activeLocation,
                    $activeStorage,
                    $activeItem,
                );

                $balance = $lockedDependencies?->stockBalance($balanceKey)
                    ?? $this->lockBalance(
                        $organization,
                        $activeLocation,
                        $activeStorage,
                        $activeItem,
                    );

                $existing = $lockedDependencies !== null && $idempotencyKey !== null
                    ? $lockedDependencies->idempotentMovement($idempotencyKey)
                    : $this->existingIdempotentMovement(
                        $organization,
                        $idempotencyKey,
                    );

                if ($existing !== null) {
                    $this->assertIdempotentMatch(
                        $existing,
                        $activeLocation,
                        $activeStorage,
                        $activeItem,
                        $baseUnit,
                        $type,
                        $quantity,
                        $referenceType,
                        $referenceId,
                        $explicitInboundCost,
                        $notes,
                    );

                    return $existing;
                }

                if (
                    $balance->last_movement_at !== null
                    && $occurredAt->lessThan(
                        $balance->last_movement_at,
                    )
                ) {
                    throw ValidationException::withMessages([
                        'occurred_at' => __(
                            'Backdated stock movements require a dedicated audited reconciliation workflow.',
                        ),
                    ]);
                }

                $currentQuantity = $this->decimal(
                    $balance->quantity_on_hand,
                    'quantity_on_hand',
                );

                $currentAverageCost = $this->decimal(
                    $balance->average_unit_cost,
                    'average_unit_cost',
                );

                $newQuantity = $this->scaleQuantity(
                    $currentQuantity->plus($quantity),
                );

                if (
                    $newQuantity->compareTo(
                        BigDecimal::zero(),
                    ) < 0
                    && ! $type->allowsNegativeBalance()
                ) {
                    throw ValidationException::withMessages([
                        'quantity' => __(
                            'This movement would create negative stock and is not allowed.',
                        ),
                    ]);
                }

                $movementUnitCost = $currentAverageCost;
                $newAverageCost = $currentAverageCost;

                if ($explicitInboundCost !== null) {
                    $movementUnitCost = $explicitInboundCost;

                    $newAverageCost = $this->weightedAverageCost(
                        $currentQuantity,
                        $currentAverageCost,
                        $quantity,
                        $explicitInboundCost,
                        $newQuantity,
                    );
                }

                $totalCost = $this->scaleMoney(
                    $this->absolute($quantity)
                        ->multipliedBy($movementUnitCost),
                    'total_cost',
                );

                $inventoryValue = $this->scaleMoney(
                    $newQuantity->multipliedBy(
                        $newAverageCost,
                    ),
                    'inventory_value',
                );

                $movement = StockMovement::query()->create([
                    'organization_id' => $organization->getKey(),
                    'location_id' => $activeLocation->getKey(),
                    'storage_location_id' => $activeStorage->getKey(),
                    'inventory_item_id' => $activeItem->getKey(),
                    'type' => $type,
                    'quantity' => (string) $quantity,
                    'base_unit_of_measure_id' => $baseUnit->getKey(),
                    'unit_cost' => (string) $this->scaleMoney(
                        $movementUnitCost,
                        'unit_cost',
                    ),
                    'total_cost' => (string) $totalCost,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'occurred_at' => $occurredAt,
                    'created_by' => $actor?->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'notes' => $notes,
                ]);

                if ($wasCreated !== null) {
                    $wasCreated = true;
                }

                $lastMovementAt = $balance->last_movement_at;

                if (
                    $lastMovementAt === null
                    || $occurredAt->greaterThan(
                        $lastMovementAt,
                    )
                ) {
                    $lastMovementAt = $occurredAt;
                }

                $balance->forceFill([
                    'quantity_on_hand' => (string) $newQuantity,
                    'average_unit_cost' => (string) $this->scaleMoney(
                        $newAverageCost,
                        'average_unit_cost',
                    ),
                    'inventory_value' => (string) $inventoryValue,
                    'last_movement_at' => $lastMovementAt,
                ])->saveQuietly();

                return $movement;
            };

            return $lockedDependencies !== null && DB::transactionLevel() > 0
                ? $operation()
                : DB::transaction($operation, 3);
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            if ($wasCreated !== null) {
                $wasCreated = false;
            }

            if ($idempotencyKey === null) {
                throw $exception;
            }

            $existing = $this->existingIdempotentMovement(
                $organization,
                $idempotencyKey,
            );

            if ($existing === null) {
                throw $exception;
            }

            $this->assertIdempotentMatch(
                $existing,
                $location,
                $storageLocation,
                $inventoryItem,
                $baseUnitOfMeasure,
                $type,
                $quantity,
                $referenceType,
                $referenceId,
                $explicitInboundCost,
                $notes,
            );

            return $existing;
        }
    }

    /**
     * Record several movements while sharing one dependency and stock-state preload.
     *
     * @param  array<int, array{storageLocation: StorageLocation, inventoryItem: InventoryItem, type: StockMovementType, baseQuantity: string, baseUnitOfMeasure: UnitOfMeasure, referenceType: string, referenceId: int, occurredAt: CarbonInterface, actor?: User|null, idempotencyKey?: string|null, notes?: string|null, inboundUnitCost?: string|null}>  $movements
     * @return Collection<int, StockMovement>
     */
    public function handleBatch(
        Organization $organization,
        Location $location,
        array $movements,
        LockedDependencies $lockedDependencies,
    ): Collection {
        $operation = function () use (
            $organization,
            $location,
            $movements,
            $lockedDependencies,
        ): Collection {
            $idempotencyKeys = collect($movements)
                ->pluck('idempotencyKey')
                ->filter()
                ->unique()
                ->values();

            $idempotentMovements = $idempotencyKeys->isEmpty()
                ? new Collection
                : StockMovement::query()
                    ->where('organization_id', $organization->getKey())
                    ->whereIn('idempotency_key', $idempotencyKeys)
                    ->get()
                    ->keyBy('idempotency_key');

            /** @var \Illuminate\Support\Collection<int, array{organization_id: int, location_id: int, storage_location_id: int, inventory_item_id: int}> $balanceIdentities */
            $balanceIdentities = collect($movements)
                ->map(fn (array $movement): array => [
                    'organization_id' => $organization->id,
                    'location_id' => $location->id,
                    'storage_location_id' => $movement['storageLocation']->id,
                    'inventory_item_id' => $movement['inventoryItem']->id,
                ])
                ->unique(fn (array $identity): string => $this->balanceKeyFromIdentity($identity))
                ->sortBy(fn (array $identity): string => $this->balanceKeyFromIdentity($identity))
                ->values();

            if ($balanceIdentities->isEmpty()) {
                return new Collection;
            }

            $now = now();

            StockBalance::query()->insertOrIgnore(
                $balanceIdentities->map(fn (array $identity): array => [
                    ...$identity,
                    'quantity_on_hand' => '0.000000',
                    'average_unit_cost' => '0.0000',
                    'inventory_value' => '0.0000',
                    'last_movement_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );

            $stockBalances = StockBalance::query()
                ->where('organization_id', $organization->getKey())
                ->where('location_id', $location->getKey())
                ->where(function (Builder $query) use ($balanceIdentities): void {
                    foreach ($balanceIdentities as $identity) {
                        $query->orWhere(function (Builder $query) use ($identity): void {
                            $query
                                ->where('storage_location_id', $identity['storage_location_id'])
                                ->where('inventory_item_id', $identity['inventory_item_id']);
                        });
                    }
                })
                ->orderBy('storage_location_id')
                ->orderBy('inventory_item_id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (StockBalance $balance): string => $this->balanceKeyFromIdentity([
                    'location_id' => (int) $balance->location_id,
                    'storage_location_id' => (int) $balance->storage_location_id,
                    'inventory_item_id' => (int) $balance->inventory_item_id,
                ]));

            $batchDependencies = $lockedDependencies->withStockState(
                $stockBalances,
                $idempotentMovements,
            );

            $stockMovements = new Collection;

            foreach ($movements as $movement) {
                $stockMovements->push($this->handle(
                    organization: $organization,
                    location: $location,
                    storageLocation: $movement['storageLocation'],
                    inventoryItem: $movement['inventoryItem'],
                    type: $movement['type'],
                    baseQuantity: $movement['baseQuantity'],
                    baseUnitOfMeasure: $movement['baseUnitOfMeasure'],
                    referenceType: $movement['referenceType'],
                    referenceId: $movement['referenceId'],
                    occurredAt: $movement['occurredAt'],
                    actor: $movement['actor'] ?? null,
                    idempotencyKey: $movement['idempotencyKey'] ?? null,
                    notes: $movement['notes'] ?? null,
                    inboundUnitCost: $movement['inboundUnitCost'] ?? null,
                    lockedDependencies: $batchDependencies,
                ));
            }

            return $stockMovements;
        };

        return DB::transactionLevel() > 0
            ? $operation()
            : DB::transaction($operation, 3);
    }

    /**
     * Create the balance row safely, then lock the authoritative projection row.
     */
    private function lockBalance(
        Organization $organization,
        Location $location,
        StorageLocation $storageLocation,
        InventoryItem $inventoryItem,
    ): StockBalance {
        $identity = [
            'organization_id' => $organization->getKey(),
            'location_id' => $location->getKey(),
            'storage_location_id' => $storageLocation->getKey(),
            'inventory_item_id' => $inventoryItem->getKey(),
        ];

        StockBalance::query()->createOrFirst(
            $identity,
            [
                'quantity_on_hand' => '0.000000',
                'average_unit_cost' => '0.0000',
                'inventory_value' => '0.0000',
                'last_movement_at' => null,
            ],
        );

        return StockBalance::query()
            ->where($identity)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function balanceKey(
        Location $location,
        StorageLocation $storageLocation,
        InventoryItem $inventoryItem,
    ): string {
        return $this->balanceKeyFromIdentity([
            'location_id' => $location->id,
            'storage_location_id' => $storageLocation->id,
            'inventory_item_id' => $inventoryItem->id,
        ]);
    }

    /** @param  array<string, int>  $identity */
    private function balanceKeyFromIdentity(array $identity): string
    {
        return implode(':', [
            $identity['location_id'],
            $identity['storage_location_id'],
            $identity['inventory_item_id'],
        ]);
    }

    /**
     * Find a previously committed movement for one business idempotency key.
     */
    private function existingIdempotentMovement(
        Organization $organization,
        ?string $idempotencyKey,
    ): ?StockMovement {
        if ($idempotencyKey === null) {
            return null;
        }

        return StockMovement::query()
            ->where(
                'organization_id',
                $organization->getKey(),
            )
            ->where(
                'idempotency_key',
                $idempotencyKey,
            )
            ->first();
    }

    /**
     * Reject reuse of an idempotency key for a materially different movement.
     */
    private function assertIdempotentMatch(
        StockMovement $existing,
        Location $location,
        StorageLocation $storageLocation,
        InventoryItem $inventoryItem,
        UnitOfMeasure $baseUnit,
        StockMovementType $type,
        BigDecimal $quantity,
        string $referenceType,
        int $referenceId,
        ?BigDecimal $explicitInboundCost,
        ?string $notes,
    ): void {
        $same = $existing->location_id
                === $location->getKey()
            && $existing->storage_location_id
                === $storageLocation->getKey()
            && $existing->inventory_item_id
                === $inventoryItem->getKey()
            && $existing->base_unit_of_measure_id
                === $baseUnit->getKey()
            && $existing->type === $type
            && $existing->quantity === (string) $quantity
            && $existing->reference_type === $referenceType
            && $existing->reference_id === $referenceId
            && $existing->notes === $notes;

        if (
            $same
            && $explicitInboundCost !== null
            && $existing->unit_cost
                !== (string) $this->scaleMoney(
                    $explicitInboundCost,
                    'unit_cost',
                )
        ) {
            $same = false;
        }

        if (! $same) {
            throw ValidationException::withMessages([
                'idempotency_key' => __(
                    'This idempotency key is already attached to a different stock movement.',
                ),
            ]);
        }
    }

    /**
     * Apply the locked moving weighted-average inbound costing rule.
     */
    private function weightedAverageCost(
        BigDecimal $currentQuantity,
        BigDecimal $currentAverageCost,
        BigDecimal $incomingQuantity,
        BigDecimal $incomingUnitCost,
        BigDecimal $newQuantity,
    ): BigDecimal {
        if (
            $currentQuantity->compareTo(
                BigDecimal::zero(),
            ) === 0
        ) {
            return $this->scaleMoney(
                $incomingUnitCost,
                'average_unit_cost',
            );
        }

        if (
            $newQuantity->compareTo(
                BigDecimal::zero(),
            ) <= 0
        ) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'Inbound stock cannot calculate weighted-average cost from a non-positive resulting quantity.',
                ),
            ]);
        }

        $existingValue = $currentQuantity->multipliedBy(
            $currentAverageCost,
        );

        $incomingValue = $incomingQuantity->multipliedBy(
            $incomingUnitCost,
        );

        return $this->scaleMoney(
            $existingValue
                ->plus($incomingValue)
                ->dividedBy(
                    $newQuantity,
                    self::MONEY_SCALE,
                    RoundingMode::HalfUp,
                ),
            'average_unit_cost',
        );
    }

    /**
     * Enforce movement sign semantics before any inventory write occurs.
     */
    private function validateDirection(
        StockMovementType $type,
        BigDecimal $quantity,
    ): void {
        if (
            $type->isInboundOnly()
            && $quantity->compareTo(
                BigDecimal::zero(),
            ) <= 0
        ) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'This inbound movement requires a positive quantity.',
                ),
            ]);
        }

        if (
            $type->isOutboundOnly()
            && $quantity->compareTo(
                BigDecimal::zero(),
            ) >= 0
        ) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'This outbound movement requires a negative quantity.',
                ),
            ]);
        }
    }

    /**
     * Keep explicit inbound costing limited to movement types that own a cost.
     */
    private function validateCostContract(
        StockMovementType $type,
        ?BigDecimal $explicitInboundCost,
    ): void {
        if (
            $type->requiresExplicitInboundCost()
            && $explicitInboundCost === null
        ) {
            throw ValidationException::withMessages([
                'unit_cost' => __(
                    'An explicit inbound unit cost is required.',
                ),
            ]);
        }

        if (
            ! $type->requiresExplicitInboundCost()
            && $explicitInboundCost !== null
        ) {
            throw ValidationException::withMessages([
                'unit_cost' => __(
                    'This movement must use the current average inventory cost.',
                ),
            ]);
        }
    }

    /**
     * Validate the mandatory business source pointer.
     */
    private function validateReference(
        string $referenceType,
        int $referenceId,
    ): void {
        if (
            $referenceType === ''
            || mb_strlen($referenceType) > 50
        ) {
            throw ValidationException::withMessages([
                'reference_type' => __(
                    'A valid stock movement reference type is required.',
                ),
            ]);
        }

        if ($referenceId < 1) {
            throw ValidationException::withMessages([
                'reference_id' => __(
                    'A valid stock movement reference is required.',
                ),
            ]);
        }
    }

    /**
     * Normalize an optional business-operation idempotency key.
     */
    private function normalizeIdempotencyKey(
        ?string $key,
    ): ?string {
        if ($key === null) {
            return null;
        }

        $key = trim($key);

        if (
            $key === ''
            || mb_strlen($key) > 180
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' => __(
                    'A valid idempotency key is required.',
                ),
            ]);
        }

        return $key;
    }

    /**
     * Normalize optional immutable ledger notes.
     */
    private function normalizeNotes(
        ?string $notes,
    ): ?string {
        if ($notes === null) {
            return null;
        }

        $notes = trim($notes);

        return $notes === ''
            ? null
            : $notes;
    }

    /**
     * Parse and scale a required non-zero base quantity.
     */
    private function quantity(
        string $value,
    ): BigDecimal {
        $quantity = $this->scaleQuantity(
            $this->decimal(
                $value,
                'quantity',
            ),
        );

        if (
            $quantity->compareTo(
                BigDecimal::zero(),
            ) === 0
        ) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'Stock movement quantity cannot be zero.',
                ),
            ]);
        }

        return $quantity;
    }

    /**
     * Parse a non-negative money value at authoritative precision.
     */
    private function nonNegativeMoney(
        string $value,
        string $field,
    ): BigDecimal {
        $money = $this->scaleMoney(
            $this->decimal(
                $value,
                $field,
            ),
            $field,
        );

        if (
            $money->compareTo(
                BigDecimal::zero(),
            ) < 0
        ) {
            throw ValidationException::withMessages([
                $field => __(
                    'Inventory cost cannot be negative.',
                ),
            ]);
        }

        return $money;
    }

    /**
     * Parse a decimal string without using PHP floating point.
     */
    private function decimal(
        string $value,
        string $field,
    ): BigDecimal {
        try {
            return BigDecimal::of(
                trim($value),
            );
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                $field => __(
                    'A valid decimal value is required.',
                ),
            ]);
        }
    }

    /**
     * Normalize a quantity to numeric(15,6) precision and bounds.
     */
    private function scaleQuantity(
        BigDecimal $quantity,
    ): BigDecimal {
        $scaled = $quantity->toScale(
            self::QUANTITY_SCALE,
            RoundingMode::HalfUp,
        );

        if (
            $scaled->isGreaterThan(
                BigDecimal::of(self::MAX_QUANTITY),
            )
            || $scaled->isLessThan(
                BigDecimal::of(self::MIN_QUANTITY),
            )
        ) {
            throw ValidationException::withMessages([
                'quantity' => __(
                    'Inventory quantity exceeds supported precision.',
                ),
            ]);
        }

        return $scaled;
    }

    /**
     * Normalize money to numeric(15,4) precision and bounds.
     */
    private function scaleMoney(
        BigDecimal $money,
        string $field,
    ): BigDecimal {
        $scaled = $money->toScale(
            self::MONEY_SCALE,
            RoundingMode::HalfUp,
        );

        if (
            $scaled->isGreaterThan(
                BigDecimal::of(self::MAX_MONEY),
            )
            || $scaled->isLessThan(
                BigDecimal::of(self::MIN_MONEY),
            )
        ) {
            throw ValidationException::withMessages([
                $field => __(
                    'Inventory value exceeds supported precision.',
                ),
            ]);
        }

        return $scaled;
    }

    /**
     * Return the absolute value without converting through native numbers.
     */
    private function absolute(
        BigDecimal $value,
    ): BigDecimal {
        return $value->compareTo(
            BigDecimal::zero(),
        ) < 0
            ? $value->negated()
            : $value;
    }
}
