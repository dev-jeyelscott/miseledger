<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->storageLocation = StorageLocation::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'name' => 'Main Storage',
        'code' => 'MAIN',
        'active' => true,
    ]);

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'dimension' => 'weight',
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
    ]);
});

test('stores one current projection row for each storage item identity', function () {
    $identity = [
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'storage_location_id' => $this->storageLocation->id,
        'inventory_item_id' => $this->item->id,
    ];

    StockBalance::query()->create($identity);

    expect(fn () => StockBalance::query()->create($identity))
        ->toThrow(QueryException::class);
});

test('allows only the stock ledger to update balance quantities', function () {
    $movement = app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '4',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: 1,
        occurredAt: now(),
        idempotencyKey: 'opening:projection-only',
        inboundUnitCost: '2.5',
    );

    $balance = StockBalance::query()->sole();

    expect($movement->quantity)
        ->toBe('4.000000')
        ->and($balance->quantity_on_hand)
        ->toBe('4.000000')
        ->and($balance->average_unit_cost)
        ->toBe('2.5000')
        ->and($balance->inventory_value)
        ->toBe('10.0000');

    $balance->quantity_on_hand = '999.000000';

    expect(fn () => $balance->save())
        ->toThrow(LogicException::class);

    $balance->refresh();

    expect($balance->quantity_on_hand)->toBe('4.000000');
});
