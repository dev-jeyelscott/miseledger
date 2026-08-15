<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;

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

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'dimension' => 'weight',
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
    ]);

    $this->action = app(RecordStockMovement::class);
});

function recordCostingMovementForTest(
    RecordStockMovement $action,
    Organization $organization,
    Location $location,
    StorageLocation $storageLocation,
    InventoryItem $item,
    UnitOfMeasure $unit,
    StockMovementType $type,
    string $quantity,
    string $idempotencyKey,
    ?string $unitCost = null,
) {
    return $action->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $item,
        type: $type,
        baseQuantity: $quantity,
        baseUnitOfMeasure: $unit,
        referenceType: 'test',
        referenceId: 1,
        occurredAt: now(),
        idempotencyKey: $idempotencyKey,
        inboundUnitCost: $unitCost,
    );
}

test('the first receipt sets the average cost to the receipt unit cost', function () {
    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::PurchaseReceipt,
        '10',
        'receipt-1',
        '4.0000',
    );

    $balance = StockBalance::query()->sole();

    expect($balance->quantity_on_hand)->toBe('10.000000')
        ->and($balance->average_unit_cost)->toBe('4.0000')
        ->and($balance->inventory_value)->toBe('40.0000');
});

test('a subsequent receipt recalculates the moving weighted average', function () {
    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::PurchaseReceipt,
        '10',
        'receipt-1',
        '4.0000',
    );

    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::PurchaseReceipt,
        '10',
        'receipt-2',
        '6.0000',
    );

    $balance = StockBalance::query()->sole();

    // (10 * 4 + 10 * 6) / 20 = 5.0000
    expect($balance->quantity_on_hand)->toBe('20.000000')
        ->and($balance->average_unit_cost)->toBe('5.0000')
        ->and($balance->inventory_value)->toBe('100.0000');
});

test('outbound movements snapshot the current average cost without recalculating it', function () {
    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::PurchaseReceipt,
        '10',
        'receipt-1',
        '4.0000',
    );

    $movement = recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::Waste,
        '-3',
        'waste-1',
    );

    $balance = StockBalance::query()->sole();

    expect($movement->unit_cost)->toBe('4.0000')
        ->and($balance->quantity_on_hand)->toBe('7.000000')
        ->and($balance->average_unit_cost)->toBe('4.0000')
        ->and($balance->inventory_value)->toBe('28.0000');
});

test('zero stock retains the last known average cost until the next inbound receipt', function () {
    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::PurchaseReceipt,
        '10',
        'receipt-1',
        '4.0000',
    );

    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::Waste,
        '-10',
        'waste-1',
    );

    $balance = StockBalance::query()->sole();

    expect($balance->quantity_on_hand)->toBe('0.000000')
        ->and($balance->average_unit_cost)->toBe('4.0000')
        ->and($balance->inventory_value)->toBe('0.0000');

    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::PurchaseReceipt,
        '5',
        'receipt-2',
        '9.0000',
    );

    $balance->refresh();

    // Depleted-stock receipts reset the average to the new receipt cost.
    expect($balance->quantity_on_hand)->toBe('5.000000')
        ->and($balance->average_unit_cost)->toBe('9.0000')
        ->and($balance->inventory_value)->toBe('45.0000');
});

test('inventory value is deterministic across mixed inbound and outbound activity', function () {
    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::PurchaseReceipt,
        '3',
        'receipt-1',
        '10.0000',
    );

    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::PurchaseReceipt,
        '7',
        'receipt-2',
        '20.0000',
    );

    recordCostingMovementForTest(
        $this->action,
        $this->organization,
        $this->location,
        $this->storageLocation,
        $this->item,
        $this->unit,
        StockMovementType::Waste,
        '-4',
        'waste-1',
    );

    $balance = StockBalance::query()->sole();

    // (3 * 10 + 7 * 20) / 10 = 17.0000, unchanged by the outbound movement.
    expect($balance->quantity_on_hand)->toBe('6.000000')
        ->and($balance->average_unit_cost)->toBe('17.0000')
        ->and($balance->inventory_value)->toBe('102.0000');
});
