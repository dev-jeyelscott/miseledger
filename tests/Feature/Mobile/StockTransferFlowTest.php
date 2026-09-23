<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->sourceLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $sourceStorage = new StorageLocation;
    $sourceStorage->organization_id = $this->organization->id;
    $sourceStorage->location_id = $this->sourceLocation->id;
    $sourceStorage->name = 'Main Storage';
    $sourceStorage->code = 'MAIN';
    $sourceStorage->active = true;
    $sourceStorage->save();
    $this->sourceStorage = $sourceStorage;

    $this->destinationLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $destinationStorage = new StorageLocation;
    $destinationStorage->organization_id = $this->organization->id;
    $destinationStorage->location_id = $this->destinationLocation->id;
    $destinationStorage->name = 'Secondary Storage';
    $destinationStorage->code = 'SECOND';
    $destinationStorage->active = true;
    $destinationStorage->save();
    $this->destinationStorage = $destinationStorage;

    $this->baseUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->baseUnit->id,
        'name' => 'Mobile Transfer Item',
        'sku' => 'MOBILE-TRANSFER',
        'active' => true,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->sourceLocation,
        storageLocation: $this->sourceStorage,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '10',
        baseUnitOfMeasure: $this->baseUnit,
        referenceType: 'opening_balance',
        referenceId: $this->item->id,
        occurredAt: now()->subDay(),
        idempotencyKey: 'opening:mobile-transfer',
        inboundUnitCost: '2.5000',
    );

    $this->actor = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::Manager,
    ]);

    test()->actingAs($this->actor)->withSession([
        'active_organization_id' => $this->organization->id,
        'mobile_location_by_org' => [$this->organization->id => $this->sourceLocation->id],
    ]);
});

test('source, scan, review, and submit produces a draft indistinguishable from desktop; ship and receive each move stock exactly once', function () {
    $this->post('/mobile/transfers/lines', [
        'from_storage_location_id' => $this->sourceStorage->id,
        'to_location_id' => $this->destinationLocation->id,
        'to_storage_location_id' => $this->destinationStorage->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '4',
    ])->assertRedirect();

    $transfer = StockTransfer::query()
        ->where('organization_id', $this->organization->id)
        ->sole();

    expect($transfer->status)->toBe(StockTransferStatus::Draft)
        ->and($transfer->from_location_id)->toBe($this->sourceLocation->id)
        ->and($transfer->to_location_id)->toBe($this->destinationLocation->id)
        ->and($transfer->lines)->toHaveCount(1)
        ->and((string) $transfer->lines->first()->requested_quantity)->toBe('4.000000');

    $this->get("/mobile/transfers/{$transfer->id}/review")
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('mobile/stock-transfers/review')
                ->where('stockTransfer.fromStorageLocationName', 'Main Storage')
                ->where('stockTransfer.toStorageLocationName', 'Secondary Storage')
                ->where('stockTransfer.lines.0.itemName', $this->item->name)
                ->where('stockTransfer.lines.0.quantity', '4.000000'),
        );

    $this->post("/mobile/transfers/{$transfer->id}/submit")
        ->assertRedirect("/mobile/transfers/{$transfer->id}");

    expect($transfer->refresh()->status)->toBe(StockTransferStatus::Draft);

    $this->post("/mobile/transfers/{$transfer->id}/ship")
        ->assertRedirect("/mobile/transfers/{$transfer->id}");

    expect($transfer->refresh()->status)->toBe(StockTransferStatus::Shipped);

    $sourceBalance = StockBalance::query()
        ->where('organization_id', $this->organization->id)
        ->where('location_id', $this->sourceLocation->id)
        ->where('inventory_item_id', $this->item->id)
        ->sole();

    expect((string) $sourceBalance->quantity_on_hand)->toBe('6.000000');

    expect(
        StockBalance::query()
            ->where('organization_id', $this->organization->id)
            ->where('location_id', $this->destinationLocation->id)
            ->where('inventory_item_id', $this->item->id)
            ->exists(),
    )->toBeFalse();

    $this->post("/mobile/transfers/{$transfer->id}/receive")
        ->assertRedirect("/mobile/transfers/{$transfer->id}");

    expect($transfer->refresh()->status)->toBe(StockTransferStatus::Received);

    $destinationBalance = StockBalance::query()
        ->where('organization_id', $this->organization->id)
        ->where('location_id', $this->destinationLocation->id)
        ->where('inventory_item_id', $this->item->id)
        ->sole();

    expect((string) $destinationBalance->quantity_on_hand)->toBe('4.000000')
        ->and((string) $sourceBalance->refresh()->quantity_on_hand)->toBe('6.000000');

    // Re-receiving replays the same snapshot idempotently and moves nothing further.
    $this->post("/mobile/transfers/{$transfer->id}/receive")
        ->assertRedirect("/mobile/transfers/{$transfer->id}");

    expect((string) $destinationBalance->refresh()->quantity_on_hand)->toBe('4.000000');
});

test('re-scanning the same item replaces the existing line instead of duplicating it', function () {
    $this->post('/mobile/transfers/lines', [
        'from_storage_location_id' => $this->sourceStorage->id,
        'to_location_id' => $this->destinationLocation->id,
        'to_storage_location_id' => $this->destinationStorage->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '4',
    ])->assertRedirect();

    $transfer = StockTransfer::query()
        ->where('organization_id', $this->organization->id)
        ->sole();

    $this->post('/mobile/transfers/lines', [
        'stock_transfer_id' => $transfer->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '7',
    ])->assertRedirect();

    $transfer->refresh();

    expect($transfer->lines)->toHaveCount(1)
        ->and((string) $transfer->lines->first()->requested_quantity)->toBe('7.000000');
});

test('a draft belonging to another organization cannot be resumed or scanned into', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorage = new StorageLocation;
    $otherStorage->organization_id = $otherOrganization->id;
    $otherStorage->location_id = $otherLocation->id;
    $otherStorage->name = 'Other Storage';
    $otherStorage->code = 'OTHER';
    $otherStorage->active = true;
    $otherStorage->save();

    $otherDestination = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherDestinationStorage = new StorageLocation;
    $otherDestinationStorage->organization_id = $otherOrganization->id;
    $otherDestinationStorage->location_id = $otherDestination->id;
    $otherDestinationStorage->name = 'Other Destination';
    $otherDestinationStorage->code = 'OTHERDEST';
    $otherDestinationStorage->active = true;
    $otherDestinationStorage->save();

    $otherTransfer = StockTransfer::query()->create([
        'organization_id' => $otherOrganization->id,
        'from_location_id' => $otherLocation->id,
        'from_storage_location_id' => $otherStorage->id,
        'to_location_id' => $otherDestination->id,
        'to_storage_location_id' => $otherDestinationStorage->id,
        'number' => 'ST-OTHER-1',
        'status' => StockTransferStatus::Draft,
        'requested_at' => now(),
    ]);

    $this->get("/mobile/transfers/{$otherTransfer->id}/review")
        ->assertNotFound();

    $this->post('/mobile/transfers/lines', [
        'stock_transfer_id' => $otherTransfer->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '3',
    ])->assertSessionHasErrors('stock_transfer_id');
});

test('a user without any transfer permission is forbidden from every transfer route', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $viewer->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    test()->actingAs($viewer)->withSession([
        'active_organization_id' => $this->organization->id,
        'mobile_location_by_org' => [$this->organization->id => $this->sourceLocation->id],
    ]);

    $this->post('/mobile/transfers/lines', [
        'from_storage_location_id' => $this->sourceStorage->id,
        'to_location_id' => $this->destinationLocation->id,
        'to_storage_location_id' => $this->destinationStorage->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '1',
    ])->assertForbidden();

    $transfer = StockTransfer::query()->create([
        'organization_id' => $this->organization->id,
        'from_location_id' => $this->sourceLocation->id,
        'from_storage_location_id' => $this->sourceStorage->id,
        'to_location_id' => $this->destinationLocation->id,
        'to_storage_location_id' => $this->destinationStorage->id,
        'number' => 'ST-FORBID-1',
        'status' => StockTransferStatus::Draft,
        'requested_at' => now(),
    ]);

    $this->post("/mobile/transfers/{$transfer->id}/ship")->assertForbidden();
    $this->post("/mobile/transfers/{$transfer->id}/receive")->assertForbidden();
});
