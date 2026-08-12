<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;

test(
    'reconciliation reports a missing projection row without mutating ledger history',
    function () {
        $organization = Organization::factory()->create();

        $location = Location::factory()->create([
            'organization_id' => $organization->id,
            'active' => true,
        ]);

        $storageLocation = new StorageLocation;
        $storageLocation->organization_id = $organization->id;
        $storageLocation->location_id = $location->id;
        $storageLocation->name = 'Main Storage';
        $storageLocation->code = 'MAIN';
        $storageLocation->active = true;
        $storageLocation->save();

        $baseUnit = UnitOfMeasure::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Gram',
            'symbol' => 'g',
            'dimension' => 'weight',
            'active' => true,
        ]);

        $inventoryItem = InventoryItem::factory()->create([
            'organization_id' => $organization->id,
            'base_unit_of_measure_id' => $baseUnit->id,
            'active' => true,
        ]);

        $movement = app(RecordStockMovement::class)->handle(
            organization: $organization,
            location: $location,
            storageLocation: $storageLocation,
            inventoryItem: $inventoryItem,
            type: StockMovementType::OpeningBalance,
            baseQuantity: '10',
            baseUnitOfMeasure: $baseUnit,
            referenceType: 'opening_balance',
            referenceId: 1,
            occurredAt: now(),
            idempotencyKey: 'opening:missing-projection',
            inboundUnitCost: '2',
        );

        DB::table(
            (new StockBalance)->getTable(),
        )->delete();

        $this->artisan('inventory:reconcile')
            ->expectsOutput(
                'Stock balance discrepancies detected.',
            )
            ->assertExitCode(1);

        expect(
            StockMovement::query()
                ->pluck('id')
                ->all(),
        )
            ->toBe([$movement->id])
            ->and(StockBalance::query()->count())
            ->toBe(0);
    },
);
