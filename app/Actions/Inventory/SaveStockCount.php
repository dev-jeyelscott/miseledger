<?php

namespace App\Actions\Inventory;

use App\Enums\OrganizationPermission;
use App\Enums\StockCountStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockCount;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveStockCount
{
    private const MAX_QUANTITY = '999999999.999999';

    public function __construct(
        private readonly ConvertQuantity $convertQuantity,
    ) {}

    /**
     * Create or replace an inventory-neutral stock-count draft.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Organization $organization,
        User $actor,
        array $attributes,
        ?StockCount $stockCount = null,
    ): StockCount {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $attributes,
            $stockCount,
        ): StockCount {
            $this->authorize($organization, $actor);

            $count = $stockCount === null
                ? null
                : StockCount::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($stockCount->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $count !== null
                && ! $count->status->isEditable()
            ) {
                throw ValidationException::withMessages([
                    'stock_count' => __(
                        'Only draft stock counts can be edited.',
                    ),
                ]);
            }

            $location = $this->activeLocation(
                $organization,
                $attributes['location_id'] ?? null,
            );

            $storageLocation = $this->activeStorageLocation(
                $organization,
                $location,
                $attributes['storage_location_id'] ?? null,
            );

            $rawLines = $attributes['lines'] ?? [];

            if (! is_array($rawLines) || $rawLines === []) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'At least one stock-count line is required.',
                    ),
                ]);
            }

            $lineSnapshots = [];
            $seenItemIds = [];

            foreach (array_values($rawLines) as $index => $rawLine) {
                if (! is_array($rawLine)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}" => __(
                            'Invalid stock-count line.',
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
                            'Each inventory item may appear only once per stock count.',
                        ),
                    ]);
                }

                $seenItemIds[$inventoryItem->id] = true;

                $countUnit = $this->activeUnitOfMeasure(
                    $organization,
                    $rawLine['count_unit_id'] ?? null,
                    $index,
                );

                $baseUnit = UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey(
                        $inventoryItem->base_unit_of_measure_id,
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

                $countedQuantity = $this->nonNegativeQuantity(
                    $rawLine['counted_quantity'] ?? null,
                    "lines.{$index}.counted_quantity",
                );

                try {
                    $countedBaseQuantity =
                        $this->convertQuantity->handle(
                            $organization,
                            $inventoryItem,
                            (string) $countedQuantity,
                            $countUnit,
                            $baseUnit,
                        );
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.count_unit_id" => $this->firstValidationMessage(
                            $exception,
                        ),
                    ]);
                }

                $lineSnapshots[] = [
                    'inventory_item_id' => $inventoryItem->id,
                    'expected_base_quantity' => '0.000000',
                    'counted_quantity' => (string) $countedQuantity,
                    'count_unit_id' => $countUnit->id,
                    'counted_base_quantity' => BigDecimal::of($countedBaseQuantity)
                        ->toScale(
                            6,
                            RoundingMode::HalfUp,
                        )
                        ->__toString(),
                    'variance_base_quantity' => '0.000000',
                    'variance_unit_cost' => '0.0000',
                    'variance_total_cost' => '0.0000',
                    'notes' => $this->nullableString(
                        $rawLine['notes'] ?? null,
                    ),
                ];
            }

            $values = [
                'organization_id' => $organization->id,
                'location_id' => $location->id,
                'storage_location_id' => $storageLocation->id,
                'number' => trim((string) $attributes['number']),
            ];

            if ($count === null) {
                $count = StockCount::query()->create([
                    ...$values,
                    'status' => StockCountStatus::Draft,
                    'counted_at' => null,
                    'created_by' => $actor->id,
                    'submitted_by' => null,
                    'finalized_by' => null,
                    'finalized_at' => null,
                ]);
            } else {
                $count->fill($values);
                $count->save();

                $count->lines()->delete();
            }

            foreach ($lineSnapshots as $lineSnapshot) {
                $count->lines()->create($lineSnapshot);
            }

            return $count->refresh();
        }, 3);
    }

    /**
     * Require physical-count creation permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::CountsCreate,
            )
        ) {
            abort(403);
        }
    }

    /**
     * Resolve an active tenant-owned restaurant location.
     */
    private function activeLocation(
        Organization $organization,
        mixed $value,
    ): Location {
        $location = Location::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find((int) $value);

        if ($location === null) {
            throw ValidationException::withMessages([
                'location_id' => __(
                    'Select an active location belonging to the active organization.',
                ),
            ]);
        }

        return $location;
    }

    /**
     * Resolve storage only beneath the selected restaurant location.
     */
    private function activeStorageLocation(
        Organization $organization,
        Location $location,
        mixed $value,
    ): StorageLocation {
        $storageLocation = StorageLocation::query()
            ->where('organization_id', $organization->id)
            ->where('location_id', $location->id)
            ->where('active', true)
            ->find((int) $value);

        if ($storageLocation === null) {
            throw ValidationException::withMessages([
                'storage_location_id' => __(
                    'Select an active storage location belonging to the selected location.',
                ),
            ]);
        }

        return $storageLocation;
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
            ->where('organization_id', $organization->id)
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
     * Resolve an active tenant-owned practical count unit.
     */
    private function activeUnitOfMeasure(
        Organization $organization,
        mixed $value,
        int $index,
    ): UnitOfMeasure {
        $unit = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find((int) $value);

        if ($unit === null) {
            throw ValidationException::withMessages([
                "lines.{$index}.count_unit_id" => __(
                    'Select an active count unit belonging to the active organization.',
                ),
            ]);
        }

        return $unit;
    }

    /**
     * Parse an entered physical quantity without floating point.
     */
    private function nonNegativeQuantity(
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
            $quantity->compareTo(BigDecimal::zero()) < 0
            || $quantity->getScale() > 6
            || $quantity->compareTo(
                BigDecimal::of(self::MAX_QUANTITY),
            ) > 0
        ) {
            throw ValidationException::withMessages([
                $field => __(
                    'Quantity must be non-negative with at most six decimal places.',
                ),
            ]);
        }

        return $quantity->toScale(
            6,
            RoundingMode::HalfUp,
        );
    }

    /**
     * Preserve the useful conversion failure while mapping it to the line UOM.
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

        return __('Invalid count unit for this inventory item.');
    }

    /**
     * Normalize optional physical-count notes.
     */
    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
