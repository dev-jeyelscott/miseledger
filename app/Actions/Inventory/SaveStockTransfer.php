<?php

namespace App\Actions\Inventory;

use App\Enums\OrganizationPermission;
use App\Enums\StockTransferStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveStockTransfer
{
    private const MAX_QUANTITY = '999999999.999999';

    public function __construct(
        private readonly ConvertQuantity $convertQuantity,
    ) {}

    /**
     * Create or replace an inventory-neutral transfer draft.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Organization $organization,
        User $actor,
        array $attributes,
        ?StockTransfer $stockTransfer = null,
    ): StockTransfer {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $attributes,
            $stockTransfer,
        ): StockTransfer {
            $this->authorize(
                $organization,
                $actor,
            );

            $transfer = $stockTransfer === null
                ? null
                : StockTransfer::query()
                    ->where(
                        'organization_id',
                        $organization->id,
                    )
                    ->whereKey($stockTransfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $transfer !== null
                && ! $transfer->status->isEditable()
            ) {
                throw ValidationException::withMessages([
                    'stock_transfer' => __(
                        'Only draft stock transfers can be edited.',
                    ),
                ]);
            }

            $this->lockLocations($organization, [
                $attributes['from_location_id'] ?? null,
                $attributes['to_location_id'] ?? null,
            ]);

            $fromLocation = $this->activeLocation(
                $organization,
                $attributes['from_location_id'] ?? null,
                'from_location_id',
            );

            $toLocation = $this->activeLocation(
                $organization,
                $attributes['to_location_id'] ?? null,
                'to_location_id',
            );

            $this->lockStorageLocations($organization, [
                $attributes['from_storage_location_id']
                    ?? null,
                $attributes['to_storage_location_id']
                    ?? null,
            ]);

            $fromStorage = $this->activeStorageLocation(
                $organization,
                $fromLocation,
                $attributes['from_storage_location_id']
                    ?? null,
                'from_storage_location_id',
            );

            $toStorage = $this->activeStorageLocation(
                $organization,
                $toLocation,
                $attributes['to_storage_location_id']
                    ?? null,
                'to_storage_location_id',
            );

            if ($fromStorage->id === $toStorage->id) {
                throw ValidationException::withMessages([
                    'to_storage_location_id' => __(
                        'The destination storage location must differ from the source storage location.',
                    ),
                ]);
            }

            $rawLines = $attributes['lines'] ?? [];

            if (! is_array($rawLines) || $rawLines === []) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'At least one stock-transfer line is required.',
                    ),
                ]);
            }

            $this->lockInventoryItems(
                $organization,
                array_map(
                    static fn (mixed $rawLine): mixed => is_array($rawLine)
                        ? ($rawLine['inventory_item_id'] ?? null)
                        : null,
                    array_values($rawLines),
                ),
            );

            $lineSnapshots = [];
            $seenItemIds = [];

            foreach (
                array_values($rawLines) as $index => $rawLine
            ) {
                if (! is_array($rawLine)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}" => __(
                            'Invalid stock-transfer line.',
                        ),
                    ]);
                }

                $inventoryItem = $this->activeInventoryItem(
                    $organization,
                    $rawLine['inventory_item_id'] ?? null,
                    $index,
                );

                if (isset($seenItemIds[$inventoryItem->id])) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.inventory_item_id" => __(
                            'Each inventory item may appear only once per stock transfer.',
                        ),
                    ]);
                }

                $seenItemIds[$inventoryItem->id] = true;

                $unit = $this->activeUnitOfMeasure(
                    $organization,
                    $rawLine['unit_id'] ?? null,
                    $index,
                );

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
                        "lines.{$index}.inventory_item_id" => __(
                            'The inventory item does not have an active base unit.',
                        ),
                    ]);
                }

                $requestedQuantity = $this->positiveQuantity(
                    $rawLine['requested_quantity'] ?? null,
                    "lines.{$index}.requested_quantity",
                );

                try {
                    $requestedBaseQuantity =
                        $this->convertQuantity->handle(
                            $organization,
                            $inventoryItem,
                            (string) $requestedQuantity,
                            $unit,
                            $baseUnit,
                        );
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.unit_id" => $this->firstValidationMessage(
                            $exception,
                        ),
                    ]);
                }

                $baseQuantity = BigDecimal::of(
                    $requestedBaseQuantity,
                )->toScale(
                    6,
                    RoundingMode::HalfUp,
                );

                if (
                    $baseQuantity->compareTo(
                        BigDecimal::zero(),
                    ) <= 0
                ) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.requested_quantity" => __(
                            'The requested quantity must convert to a positive base quantity.',
                        ),
                    ]);
                }

                $lineSnapshots[] = [
                    'organization_id' => $organization->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'requested_quantity' => (string) $requestedQuantity,
                    'unit_id' => $unit->id,
                    'requested_base_quantity' => (string) $baseQuantity,
                    'shipped_base_quantity' => null,
                    'received_base_quantity' => null,
                    'unit_cost' => null,
                    'variance_base_quantity' => null,
                ];
            }

            $values = [
                'organization_id' => $organization->id,
                'from_location_id' => $fromLocation->id,
                'from_storage_location_id' => $fromStorage->id,
                'to_location_id' => $toLocation->id,
                'to_storage_location_id' => $toStorage->id,
                'number' => trim(
                    (string) $attributes['number'],
                ),
                'notes' => $this->nullableString(
                    $attributes['notes'] ?? null,
                ),
            ];

            if ($transfer === null) {
                $transfer = StockTransfer::query()->create([
                    ...$values,
                    'status' => StockTransferStatus::Draft,
                    'requested_at' => now(),
                    'shipped_at' => null,
                    'received_at' => null,
                    'created_by' => $actor->id,
                    'shipped_by' => null,
                    'received_by' => null,
                ]);
            } else {
                $transfer->fill($values);
                $transfer->save();

                $transfer->lines()->delete();
            }

            foreach ($lineSnapshots as $lineSnapshot) {
                $transfer
                    ->lines()
                    ->create($lineSnapshot);
            }

            return $transfer->refresh();
        }, 3);
    }

    /**
     * Require transfer creation permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::TransfersCreate,
            )
        ) {
            abort(403);
        }
    }

    /**
     * Lock source and destination location rows before validating their
     * active state, so a concurrent deactivation is serialized against this
     * draft save instead of racing it.
     *
     * @param  array<int, mixed>  $locationIds
     */
    private function lockLocations(
        Organization $organization,
        array $locationIds,
    ): void {
        $ids = $this->normalizedLockIds($locationIds);

        if ($ids === []) {
            return;
        }

        Location::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /**
     * Lock source and destination storage-location rows before validating
     * their active state. Always locked after locations, in ascending id
     * order, to keep a deterministic lock order and avoid deadlocks.
     *
     * @param  array<int, mixed>  $storageLocationIds
     */
    private function lockStorageLocations(
        Organization $organization,
        array $storageLocationIds,
    ): void {
        $ids = $this->normalizedLockIds($storageLocationIds);

        if ($ids === []) {
            return;
        }

        StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /**
     * Deduplicate and sort candidate ids so lock acquisition order is
     * deterministic regardless of request payload ordering.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function normalizedLockIds(array $ids): array
    {
        $normalized = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            array_filter(
                $ids,
                static fn (mixed $id): bool => $id !== null,
            ),
        )));

        sort($normalized);

        return $normalized;
    }

    /**
     * Resolve an active tenant-owned restaurant location.
     */
    private function activeLocation(
        Organization $organization,
        mixed $value,
        string $field,
    ): Location {
        $location = Location::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->find((int) $value);

        if ($location === null) {
            throw ValidationException::withMessages([
                $field => __(
                    'Select an active location belonging to the active organization.',
                ),
            ]);
        }

        return $location;
    }

    /**
     * Resolve active storage beneath the selected location.
     */
    private function activeStorageLocation(
        Organization $organization,
        Location $location,
        mixed $value,
        string $field,
    ): StorageLocation {
        $storageLocation = StorageLocation::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where(
                'location_id',
                $location->id,
            )
            ->where('active', true)
            ->find((int) $value);

        if ($storageLocation === null) {
            throw ValidationException::withMessages([
                $field => __(
                    'Select an active storage location belonging to the selected location.',
                ),
            ]);
        }

        return $storageLocation;
    }

    /**
     * Lock referenced inventory-item rows before validating their active
     * state, so a concurrent deactivation is serialized against this draft
     * save instead of racing it. Locked in ascending id order, after
     * locations and storage locations, to keep a deterministic lock order
     * across multi-line transfers and avoid deadlocks.
     *
     * @param  array<int, mixed>  $itemIds
     */
    private function lockInventoryItems(
        Organization $organization,
        array $itemIds,
    ): void {
        $ids = $this->normalizedLockIds($itemIds);

        if ($ids === []) {
            return;
        }

        InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /**
     * Resolve an active tenant-owned inventory item.
     */
    private function activeInventoryItem(
        Organization $organization,
        mixed $value,
        int $index,
    ): InventoryItem {
        $inventoryItem = InventoryItem::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->find((int) $value);

        if ($inventoryItem === null) {
            throw ValidationException::withMessages([
                "lines.{$index}.inventory_item_id" => __(
                    'Select an active inventory item belonging to the active organization.',
                ),
            ]);
        }

        return $inventoryItem;
    }

    /**
     * Resolve an active tenant-owned practical transfer unit.
     */
    private function activeUnitOfMeasure(
        Organization $organization,
        mixed $value,
        int $index,
    ): UnitOfMeasure {
        $unit = UnitOfMeasure::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->where('active', true)
            ->find((int) $value);

        if ($unit === null) {
            throw ValidationException::withMessages([
                "lines.{$index}.unit_id" => __(
                    'Select an active unit belonging to the active organization.',
                ),
            ]);
        }

        return $unit;
    }

    /**
     * Parse a strictly positive fixed-precision transfer quantity.
     */
    private function positiveQuantity(
        mixed $value,
        string $field,
    ): BigDecimal {
        try {
            $quantity = BigDecimal::of(
                trim((string) $value),
            );
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                $field => __('A valid quantity is required.'),
            ]);
        }

        if (
            $quantity->compareTo(BigDecimal::zero()) <= 0
            || $quantity->getScale() > 6
            || $quantity->compareTo(
                BigDecimal::of(self::MAX_QUANTITY),
            ) > 0
        ) {
            throw ValidationException::withMessages([
                $field => __(
                    'Quantity must be greater than zero with at most six decimal places.',
                ),
            ]);
        }

        return $quantity->toScale(
            6,
            RoundingMode::HalfUp,
        );
    }

    /**
     * Preserve the useful conversion failure on the line unit field.
     */
    private function firstValidationMessage(
        ValidationException $exception,
    ): string {
        foreach ($exception->errors() as $messages) {
            $message = $messages[0] ?? null;

            if (is_string($message)) {
                return $message;
            }
        }

        return __(
            'Invalid unit for this inventory item.',
        );
    }

    /**
     * Normalize optional transfer notes.
     */
    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
