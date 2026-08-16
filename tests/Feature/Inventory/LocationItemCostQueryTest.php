<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Support\Inventory\LocationItemCostQuery;
use App\Support\Inventory\LocationItemCostQueryException;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

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

function makeStorageLocationForCostTest(Organization $organization, Location $location, string $code): StorageLocation
{
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = "Storage {$code}";
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

test('the average cost is the receipt cost when only one storage location holds stock', function () {
    $storageLocation = makeStorageLocationForCostTest($this->organization, $this->location, 'A');

    $this->action->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::PurchaseReceipt,
        baseQuantity: '10',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'test',
        referenceId: 1,
        occurredAt: now(),
        idempotencyKey: 'receipt-1',
        inboundUnitCost: '4.0000',
    );

    $result = LocationItemCostQuery::resolve($this->organization, $this->location, $this->item);

    expect($result->quantityOnHand)->toBe('10.000000')
        ->and($result->inventoryValue)->toBe('40.0000')
        ->and($result->averageUnitCost)->toBe('4.0000');
});

test('the average cost blends value across every storage location within the restaurant location', function () {
    $storageA = makeStorageLocationForCostTest($this->organization, $this->location, 'A');
    $storageB = makeStorageLocationForCostTest($this->organization, $this->location, 'B');

    $this->action->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $storageA,
        inventoryItem: $this->item,
        type: StockMovementType::PurchaseReceipt,
        baseQuantity: '10',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'test',
        referenceId: 1,
        occurredAt: now(),
        idempotencyKey: 'receipt-a',
        inboundUnitCost: '4.0000',
    );

    $this->action->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $storageB,
        inventoryItem: $this->item,
        type: StockMovementType::PurchaseReceipt,
        baseQuantity: '10',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'test',
        referenceId: 2,
        occurredAt: now(),
        idempotencyKey: 'receipt-b',
        inboundUnitCost: '6.0000',
    );

    $result = LocationItemCostQuery::resolve($this->organization, $this->location, $this->item);

    // (10 * 4 + 10 * 6) / 20 = 5.0000
    expect($result->quantityOnHand)->toBe('20.000000')
        ->and($result->inventoryValue)->toBe('100.0000')
        ->and($result->averageUnitCost)->toBe('5.0000');
});

test('the average cost is explicitly zero when the location holds no stock for the item', function () {
    $result = LocationItemCostQuery::resolve($this->organization, $this->location, $this->item);

    expect($result->quantityOnHand)->toBe('0.000000')
        ->and($result->inventoryValue)->toBe('0.0000')
        ->and($result->averageUnitCost)->toBe('0.0000');
});

test('the average cost is explicitly zero once stock is depleted across all storage locations', function () {
    $storageA = makeStorageLocationForCostTest($this->organization, $this->location, 'A');

    $this->action->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $storageA,
        inventoryItem: $this->item,
        type: StockMovementType::PurchaseReceipt,
        baseQuantity: '10',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'test',
        referenceId: 1,
        occurredAt: now(),
        idempotencyKey: 'receipt-a',
        inboundUnitCost: '4.0000',
    );

    $this->action->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $storageA,
        inventoryItem: $this->item,
        type: StockMovementType::Waste,
        baseQuantity: '-10',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'test',
        referenceId: 2,
        occurredAt: now(),
        idempotencyKey: 'waste-a',
    );

    $result = LocationItemCostQuery::resolve($this->organization, $this->location, $this->item);

    expect($result->quantityOnHand)->toBe('0.000000')
        ->and($result->inventoryValue)->toBe('0.0000')
        ->and($result->averageUnitCost)->toBe('0.0000');
});

test('resolving cost for a location outside the organization is rejected', function () {
    $otherOrganization = Organization::factory()->create();

    LocationItemCostQuery::resolve($otherOrganization, $this->location, $this->item);
})->throws(LocationItemCostQueryException::class);

test('resolving cost for an inventory item outside the organization is rejected', function () {
    $otherOrganization = Organization::factory()->create();

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $otherOrganization->id,
        'base_unit_of_measure_id' => $this->unit->id,
    ]);

    LocationItemCostQuery::resolve($this->organization, $this->location, $otherItem);
})->throws(LocationItemCostQueryException::class);
