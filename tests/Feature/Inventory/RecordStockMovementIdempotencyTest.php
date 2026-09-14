<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->storageLocation = new StorageLocation;
    $this->storageLocation->organization_id = $this->organization->id;
    $this->storageLocation->location_id = $this->location->id;
    $this->storageLocation->name = 'Main Storage';
    $this->storageLocation->code = 'MAIN';
    $this->storageLocation->active = true;
    $this->storageLocation->save();

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
    ]);

    $this->inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
    ]);
});

test(
    'reusing an idempotency key with a different occurred_at is rejected',
    function () {
        $record = app(RecordStockMovement::class);

        $firstOccurredAt = now();

        $first = $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '1',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'manual_adjustment',
            referenceId: 1,
            occurredAt: $firstOccurredAt,
            idempotencyKey: 'movement-123',
        );

        expect(fn () => $record->handle(
            organization: $this->organization,
            location: $this->location,
            storageLocation: $this->storageLocation,
            inventoryItem: $this->inventoryItem,
            type: StockMovementType::ManualAdjustment,
            baseQuantity: '1',
            baseUnitOfMeasure: $this->baseUnit,
            referenceType: 'manual_adjustment',
            referenceId: 1,
            occurredAt: $firstOccurredAt->clone()->addDay(),
            idempotencyKey: 'movement-123',
        ))->toThrow(
            ValidationException::class,
            'This idempotency key is already attached to a different stock movement.',
        );

        expect(StockMovement::query()->count())
            ->toBe(1)
            ->and(
                StockMovement::query()
                    ->sole()
                    ->occurred_at
                    ->eq($first->occurred_at),
            )
            ->toBeTrue();
    },
);

test(
    'reusing an idempotency key with a different occurred_at is rejected during unique-constraint race recovery',
    function () {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'PostgreSQL unique-constraint race recovery is verified in CI.',
            );
        }

        $record = app(RecordStockMovement::class);

        $racedOccurredAt = now();
        $requestedOccurredAt = $racedOccurredAt->clone()->addDay();

        // A genuine unique-constraint race requires a competing row that is
        // durably committed by another connection while this test's own
        // fixtures remain visible to it. RefreshDatabase normally wraps the
        // whole test in one uncommitted transaction, which would hide those
        // fixtures from a second connection and trip a foreign-key error
        // instead of the unique-constraint race we're trying to prove. We
        // commit that wrapping transaction up front so both connections see
        // real, durable rows; Laravel's RefreshDatabase already detects an
        // out-of-transaction connection at teardown and forces a fresh
        // migration before the next test, so no manual cleanup is required.
        DB::commit();

        // The competing row targets its own, otherwise-untouched balance
        // identity (same organization, different location/storage/item).
        // Reusing the primary movement's own location/storage/item here
        // would deadlock: those rows are held under lockForUpdate() by the
        // primary handle() call for the whole life of its transaction, and
        // the competing insert's foreign-key checks would block waiting for
        // that lock to release while the primary connection is itself
        // blocked waiting for the competing insert to finish.
        $raceLocation = Location::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $raceStorageLocation = new StorageLocation;
        $raceStorageLocation->organization_id = $this->organization->id;
        $raceStorageLocation->location_id = $raceLocation->id;
        $raceStorageLocation->name = 'Race Storage';
        $raceStorageLocation->code = 'RACE';
        $raceStorageLocation->active = true;
        $raceStorageLocation->save();

        $raceUnit = UnitOfMeasure::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Race Unit',
            'symbol' => 'ru',
            'dimension' => 'weight',
        ]);

        $raceItem = InventoryItem::factory()->create([
            'organization_id' => $this->organization->id,
            'base_unit_of_measure_id' => $raceUnit->id,
        ]);

        $raceConnectionName = 'pgsql_idempotency_race_test';

        config([
            "database.connections.{$raceConnectionName}" => config(
                'database.connections.'.config('database.default'),
            ),
        ]);

        StockMovement::creating(
            function (StockMovement $model) use (
                $racedOccurredAt,
                $raceConnectionName,
                $raceLocation,
                $raceStorageLocation,
                $raceItem,
                $raceUnit,
            ): void {
                if ($model->idempotency_key !== 'movement-race') {
                    return;
                }

                DB::connection($raceConnectionName)
                    ->table('stock_movements')
                    ->insert([
                        'organization_id' => $model->organization_id,
                        'location_id' => $raceLocation->id,
                        'storage_location_id' => $raceStorageLocation->id,
                        'inventory_item_id' => $raceItem->id,
                        'type' => $model->type->value,
                        'quantity' => $model->quantity,
                        'base_unit_of_measure_id' => $raceUnit->id,
                        'unit_cost' => $model->unit_cost,
                        'total_cost' => $model->total_cost,
                        'reference_type' => $model->reference_type,
                        'reference_id' => $model->reference_id,
                        'occurred_at' => $racedOccurredAt->clone()->utc()->toDateTimeString(),
                        'idempotency_key' => $model->idempotency_key,
                        'notes' => $model->notes,
                        'created_at' => now()->utc()->toDateTimeString(),
                    ]);
            },
        );

        try {
            expect(fn () => $record->handle(
                organization: $this->organization,
                location: $this->location,
                storageLocation: $this->storageLocation,
                inventoryItem: $this->inventoryItem,
                type: StockMovementType::ManualAdjustment,
                baseQuantity: '1',
                baseUnitOfMeasure: $this->baseUnit,
                referenceType: 'manual_adjustment',
                referenceId: 1,
                occurredAt: $requestedOccurredAt,
                idempotencyKey: 'movement-race',
            ))->toThrow(
                ValidationException::class,
                'This idempotency key is already attached to a different stock movement.',
            );
        } finally {
            StockMovement::getEventDispatcher()?->forget(
                'eloquent.creating: '.StockMovement::class,
            );

            DB::purge($raceConnectionName);
        }

        expect(StockMovement::query()->count())->toBe(1);
    },
);
