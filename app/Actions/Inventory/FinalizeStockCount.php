<?php

namespace App\Actions\Inventory;

use App\Enums\OrganizationPermission;
use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinalizeStockCount
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    /**
     * Snapshot expected stock and reconcile every non-zero physical variance.
     */
    public function handle(
        Organization $organization,
        User $actor,
        StockCount $stockCount,
    ): StockCount {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $stockCount,
        ): StockCount {
            $this->authorize($organization, $actor);

            $count = StockCount::query()
                ->where('organization_id', $organization->id)
                ->whereKey($stockCount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($count->status === StockCountStatus::Finalized) {
                return $count->refresh();
            }

            if ($count->status !== StockCountStatus::Submitted) {
                throw ValidationException::withMessages([
                    'stock_count' => __(
                        'Only submitted stock counts can be finalized.',
                    ),
                ]);
            }

            $location = Location::query()
                ->where('organization_id', $organization->id)
                ->whereKey($count->location_id)
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if ($location === null) {
                throw ValidationException::withMessages([
                    'location_id' => __(
                        'The stock-count location is no longer active.',
                    ),
                ]);
            }

            $storageLocation = StorageLocation::query()
                ->where('organization_id', $organization->id)
                ->where('location_id', $location->id)
                ->whereKey($count->storage_location_id)
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if ($storageLocation === null) {
                throw ValidationException::withMessages([
                    'storage_location_id' => __(
                        'The stock-count storage location is no longer active.',
                    ),
                ]);
            }

            $lines = StockCountLine::query()
                ->where('stock_count_id', $count->id)
                ->orderBy('inventory_item_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'At least one stock-count line is required.',
                    ),
                ]);
            }

            $finalizedAt = now();
            $movementCount = 0;

            foreach ($lines as $line) {
                $inventoryItem = InventoryItem::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey($line->inventory_item_id)
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if ($inventoryItem === null) {
                    throw ValidationException::withMessages([
                        'stock_count' => __(
                            'One or more counted inventory items are no longer active.',
                        ),
                    ]);
                }

                $baseUnit = UnitOfMeasure::query()
                    ->where('organization_id', $organization->id)
                    ->whereKey(
                        $inventoryItem->base_unit_of_measure_id,
                    )
                    ->where('active', true)
                    ->first();

                if ($baseUnit === null) {
                    throw ValidationException::withMessages([
                        'stock_count' => __(
                            'One or more counted inventory items do not have an active base unit.',
                        ),
                    ]);
                }

                $balance = StockBalance::query()
                    ->where('organization_id', $organization->id)
                    ->where('location_id', $location->id)
                    ->where(
                        'storage_location_id',
                        $storageLocation->id,
                    )
                    ->where(
                        'inventory_item_id',
                        $inventoryItem->id,
                    )
                    ->lockForUpdate()
                    ->first();

                $expectedBaseQuantity = BigDecimal::of(
                    $balance->quantity_on_hand
                        ?? '0.000000',
                )->toScale(
                    6,
                    RoundingMode::HalfUp,
                );

                $countedBaseQuantity = BigDecimal::of(
                    $line->counted_base_quantity,
                )->toScale(
                    6,
                    RoundingMode::HalfUp,
                );

                $varianceBaseQuantity = $countedBaseQuantity
                    ->minus($expectedBaseQuantity)
                    ->toScale(
                        6,
                        RoundingMode::HalfUp,
                    );

                $varianceUnitCost = BigDecimal::of(
                    $balance->average_unit_cost
                        ?? '0.0000',
                )->toScale(
                    4,
                    RoundingMode::HalfUp,
                );

                /*
                 * Keep the line variance value signed so the report preserves
                 * whether inventory value increased or decreased.
                 */
                $varianceTotalCost = $varianceBaseQuantity
                    ->multipliedBy($varianceUnitCost)
                    ->toScale(
                        4,
                        RoundingMode::HalfUp,
                    );

                if (
                    $varianceBaseQuantity->compareTo(
                        BigDecimal::zero(),
                    ) !== 0
                ) {
                    $this->recordStockMovement->handle(
                        organization: $organization,
                        location: $location,
                        storageLocation: $storageLocation,
                        inventoryItem: $inventoryItem,
                        type: StockMovementType::CountAdjustment,
                        baseQuantity: (string) $varianceBaseQuantity,
                        baseUnitOfMeasure: $baseUnit,
                        referenceType: 'stock_count_line',
                        referenceId: $line->id,
                        occurredAt: $finalizedAt,
                        actor: $actor,
                        idempotencyKey: "stock_count:{$count->id}:line:{$line->id}",
                        notes: __(
                            'Physical stock count :number',
                            ['number' => $count->number],
                        ),
                    );

                    $movementCount++;
                }

                $line->forceFill([
                    'expected_base_quantity' => (string) $expectedBaseQuantity,
                    'variance_base_quantity' => (string) $varianceBaseQuantity,
                    'variance_unit_cost' => (string) $varianceUnitCost,
                    'variance_total_cost' => (string) $varianceTotalCost,
                ])->save();
            }

            $count->forceFill([
                'status' => StockCountStatus::Finalized,
                'finalized_by' => $actor->id,
                'finalized_at' => $finalizedAt,
            ])->save();

            AuditLog::query()->create([
                'organization_id' => $organization->id,
                'actor_id' => $actor->id,
                'action' => 'stock_count.finalized',
                'entity_type' => 'stock_count',
                'entity_id' => $count->id,
                'before_data' => [
                    'status' => StockCountStatus::Submitted->value,
                ],
                'after_data' => [
                    'status' => StockCountStatus::Finalized->value,
                    'finalized_at' => $finalizedAt->toIso8601String(),
                    'line_count' => $lines->count(),
                    'movement_count' => $movementCount,
                ],
                'correlation_id' => "stock-count:{$count->id}:finalize",
            ]);

            return $count->refresh();
        }, 3);
    }

    /**
     * Require explicit stock-count finalization permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::CountsFinalize,
            )
        ) {
            abort(403);
        }
    }
}
