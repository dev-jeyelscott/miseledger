<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\ReplayStockLedger;
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

test(
    'replay orders movements by occurred_at then id rather than insertion order',
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

        $earliest = now();

        // Inserted first (lowest id) but occurs latest: a +10 @ 100 receipt.
        StockMovement::query()->create([
            'organization_id' => $organization->id,
            'location_id' => $location->id,
            'storage_location_id' => $storageLocation->id,
            'inventory_item_id' => $inventoryItem->id,
            'type' => StockMovementType::PurchaseReceipt,
            'quantity' => '10.000000',
            'base_unit_of_measure_id' => $baseUnit->id,
            'unit_cost' => '100.0000',
            'total_cost' => '1000.0000',
            'reference_type' => 'goods_receipt_line',
            'reference_id' => 1,
            'occurred_at' => $earliest->clone()->addMinutes(2),
        ]);

        // Inserted second (higher id) but occurs earliest: a +20 @ 200 receipt.
        StockMovement::query()->create([
            'organization_id' => $organization->id,
            'location_id' => $location->id,
            'storage_location_id' => $storageLocation->id,
            'inventory_item_id' => $inventoryItem->id,
            'type' => StockMovementType::PurchaseReceipt,
            'quantity' => '20.000000',
            'base_unit_of_measure_id' => $baseUnit->id,
            'unit_cost' => '200.0000',
            'total_cost' => '4000.0000',
            'reference_type' => 'goods_receipt_line',
            'reference_id' => 2,
            'occurred_at' => $earliest,
        ]);

        // Inserted third but occurs between the two receipts above.
        StockMovement::query()->create([
            'organization_id' => $organization->id,
            'location_id' => $location->id,
            'storage_location_id' => $storageLocation->id,
            'inventory_item_id' => $inventoryItem->id,
            'type' => StockMovementType::Waste,
            'quantity' => '-5.000000',
            'base_unit_of_measure_id' => $baseUnit->id,
            'unit_cost' => null,
            'total_cost' => null,
            'reference_type' => 'waste_record',
            'reference_id' => 1,
            'occurred_at' => $earliest->clone()->addMinute(),
        ]);

        $expected = app(ReplayStockLedger::class)->handle(
            $organization->id,
            $location->id,
            $storageLocation->id,
            $inventoryItem->id,
        );

        // Chronological replay: +20@200 (avg 200), -5 (avg unchanged 200), +10@100
        // => avg = (15*200 + 10*100) / 25 = 160.0000, not the 166.6667 an
        // id-ordered replay would produce.
        expect($expected['quantity_on_hand'])
            ->toBe('25.000000')
            ->and($expected['average_unit_cost'])
            ->toBe('160.0000')
            ->and($expected['inventory_value'])
            ->toBe('4000.0000');
    },
);
