<?php

use App\Actions\Inventory\ReceiveStockTransfer;
use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Inventory\SaveStockTransfer;
use App\Actions\Inventory\ShipStockTransfer;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('configured cross-dimension transfer unit resolves to base before transfer movements', function () {
    $organization = Organization::factory()->create([
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);

    $fromLocation = Location::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Branch A',
        'active' => true,
    ]);

    $toLocation = Location::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Branch B',
        'active' => true,
    ]);

    $fromStorage = new StorageLocation;
    $fromStorage->organization_id = $organization->id;
    $fromStorage->location_id = $fromLocation->id;
    $fromStorage->name = 'Source Storage';
    $fromStorage->code = 'SOURCE';
    $fromStorage->active = true;
    $fromStorage->save();

    $toStorage = new StorageLocation;
    $toStorage->organization_id = $organization->id;
    $toStorage->location_id = $toLocation->id;
    $toStorage->name = 'Destination Storage';
    $toStorage->code = 'DESTINATION';
    $toStorage->active = true;
    $toStorage->save();

    $milliliter = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Milliliter',
        'symbol' => 'ml',
        'dimension' => 'volume',
        'active' => true,
    ]);

    $bottle = UnitOfMeasure::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Bottle',
        'symbol' => 'bottle',
        'dimension' => 'count',
        'active' => true,
    ]);

    $item = InventoryItem::factory()->create([
        'organization_id' => $organization->id,
        'base_unit_of_measure_id' => $milliliter->id,
        'name' => 'Cooking Oil',
        'sku' => 'OIL-TRANSFER',
        'active' => true,
    ]);

    InventoryItemUnit::factory()->create([
        'inventory_item_id' => $item->id,
        'unit_of_measure_id' => $bottle->id,
        'quantity_in_base_unit' => '1000.000000',
        'active' => true,
    ]);

    $actor = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $actor->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    $this->actingAs($actor)
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->get(route('stock-transfers.create'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('stock-transfers/form')
                ->has('inventoryItemOptions', 1)
                ->where('inventoryItemOptions.0.id', $item->id)
                ->where(
                    'inventoryItemOptions.0.validUnitIds',
                    [$bottle->id, $milliliter->id],
                ),
        );

    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $fromLocation,
        storageLocation: $fromStorage,
        inventoryItem: $item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '5000.000000',
        baseUnitOfMeasure: $milliliter,
        referenceType: 'opening_balance',
        referenceId: $item->id,
        occurredAt: now()->subMinute(),
        idempotencyKey: 'cross-dimension-transfer:opening',
        inboundUnitCost: '0.0100',
    );

    $transfer = app(SaveStockTransfer::class)->handle(
        $organization,
        $actor,
        [
            'number' => 'TR-CROSS-DIMENSION',
            'from_location_id' => $fromLocation->id,
            'from_storage_location_id' => $fromStorage->id,
            'to_location_id' => $toLocation->id,
            'to_storage_location_id' => $toStorage->id,
            'notes' => null,
            'lines' => [
                [
                    'inventory_item_id' => $item->id,
                    'requested_quantity' => '2.000000',
                    'unit_id' => $bottle->id,
                ],
            ],
        ],
    );

    $line = $transfer->lines()->sole();

    expect($line->requested_quantity)
        ->toBe('2.000000')
        ->and($line->unit_id)
        ->toBe($bottle->id)
        ->and($line->requested_base_quantity)
        ->toBe('2000.000000')
        ->and(
            StockMovement::query()
                ->where('inventory_item_id', $item->id)
                ->where('type', StockMovementType::TransferOut->value)
                ->count(),
        )
        ->toBe(0)
        ->and(
            StockMovement::query()
                ->where('inventory_item_id', $item->id)
                ->where('type', StockMovementType::TransferIn->value)
                ->count(),
        )
        ->toBe(0);

    $shipped = app(ShipStockTransfer::class)->handle(
        $organization,
        $actor,
        $transfer,
    );

    $line = $shipped->lines()->sole();

    $outbound = StockMovement::query()
        ->where('inventory_item_id', $item->id)
        ->where('type', StockMovementType::TransferOut->value)
        ->sole();

    expect($line->requested_quantity)
        ->toBe('2.000000')
        ->and($line->unit_id)
        ->toBe($bottle->id)
        ->and($line->requested_base_quantity)
        ->toBe('2000.000000')
        ->and($line->shipped_base_quantity)
        ->toBe('2000.000000')
        ->and($outbound->quantity)
        ->toBe('-2000.000000')
        ->and($outbound->base_unit_of_measure_id)
        ->toBe($milliliter->id);

    $received = app(ReceiveStockTransfer::class)->handle(
        $organization,
        $actor,
        $shipped,
        [
            'lines' => [
                [
                    'id' => $line->id,
                    'received_base_quantity' => '2000.000000',
                ],
            ],
        ],
    );

    $line = $received->lines()->sole();

    $inbound = StockMovement::query()
        ->where('inventory_item_id', $item->id)
        ->where('type', StockMovementType::TransferIn->value)
        ->sole();

    expect($line->requested_quantity)
        ->toBe('2.000000')
        ->and($line->unit_id)
        ->toBe($bottle->id)
        ->and($line->requested_base_quantity)
        ->toBe('2000.000000')
        ->and($line->received_base_quantity)
        ->toBe('2000.000000')
        ->and($line->variance_base_quantity)
        ->toBe('0.000000')
        ->and($inbound->quantity)
        ->toBe('2000.000000')
        ->and($inbound->base_unit_of_measure_id)
        ->toBe($milliliter->id);
});
