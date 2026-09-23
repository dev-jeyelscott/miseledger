<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockCount;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $this->organization->id;
    $storageLocation->location_id = $this->location->id;
    $storageLocation->name = 'Main Storage';
    $storageLocation->code = 'MAIN';
    $storageLocation->active = true;
    $storageLocation->save();
    $this->storageLocation = $storageLocation;

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
        'name' => 'Mobile Count Item',
        'sku' => 'MOBILE-COUNT',
        'active' => true,
    ]);

    $this->actor = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->actor->id,
        'role' => OrganizationRole::Manager,
    ]);

    test()->actingAs($this->actor)->withSession([
        'active_organization_id' => $this->organization->id,
        'mobile_location_by_org' => [$this->organization->id => $this->location->id],
    ]);
});

test('location, scan, review, and submit produces a submittable draft indistinguishable from desktop', function () {
    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '10',
        baseUnitOfMeasure: $this->baseUnit,
        referenceType: 'opening_balance',
        referenceId: 1,
        occurredAt: now(),
        idempotencyKey: 'opening:mobile-count',
        inboundUnitCost: '2.5',
    );

    $this->post('/mobile/stock-counts', [
        'storage_location_id' => $this->storageLocation->id,
    ])->assertRedirect(
        "/mobile/stock-counts/scan?storage_location_id={$this->storageLocation->id}",
    );

    $this->post('/mobile/stock-counts/lines', [
        'storage_location_id' => $this->storageLocation->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '12',
    ])->assertRedirect();

    $count = StockCount::query()
        ->where('organization_id', $this->organization->id)
        ->sole();

    expect($count->status)->toBe(StockCountStatus::Draft)
        ->and($count->lines)->toHaveCount(1)
        ->and((string) $count->lines->first()->counted_quantity)->toBe('12.000000');

    $this->get("/mobile/stock-counts/{$count->id}/review")
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('mobile/stock-counts/review')
                ->where('stockCount.lines.0.itemName', $this->item->name)
                ->where('stockCount.lines.0.expectedBaseQuantity', '10.000000')
                ->where('stockCount.lines.0.physicalQuantity', '12.000000')
                ->where('stockCount.lines.0.varianceBaseQuantity', '2.000000'),
        );

    $this->post("/mobile/stock-counts/{$count->id}/submit")
        ->assertRedirect();

    expect($count->refresh()->status)->toBe(StockCountStatus::Submitted)
        ->and($count->submitted_by)->toBe($this->actor->id)
        ->and($count->counted_at)->not->toBeNull();
});

test('re-scanning the same item and unit updates the existing line instead of duplicating it', function () {
    $this->post('/mobile/stock-counts/lines', [
        'storage_location_id' => $this->storageLocation->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '12',
    ])->assertRedirect();

    $count = StockCount::query()
        ->where('organization_id', $this->organization->id)
        ->sole();

    $this->post('/mobile/stock-counts/lines', [
        'stock_count_id' => $count->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '15',
    ])->assertRedirect();

    $count->refresh();

    expect($count->lines)->toHaveCount(1)
        ->and((string) $count->lines->first()->counted_quantity)->toBe('15.000000');
});

test('re-scanning the same item in a different unit still replaces the one line, not a second one', function () {
    // `stock_count_lines` has a unique(stock_count_id, inventory_item_id)
    // constraint (one line per item per count, not per item+unit), so a
    // re-scan in a different unit must replace the item's single line
    // rather than add a second one.
    $gramUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Gram',
        'symbol' => 'g',
        'dimension' => 'weight',
        'active' => true,
    ]);

    $this->post('/mobile/stock-counts/lines', [
        'storage_location_id' => $this->storageLocation->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '5',
    ])->assertRedirect();

    $count = StockCount::query()
        ->where('organization_id', $this->organization->id)
        ->sole();

    $this->post('/mobile/stock-counts/lines', [
        'stock_count_id' => $count->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $gramUnit->id,
        'quantity' => '200',
    ])->assertRedirect();

    $count->refresh();

    expect($count->lines)->toHaveCount(1)
        ->and($count->lines->first()->count_unit_id)->toBe($gramUnit->id)
        ->and((string) $count->lines->first()->counted_quantity)->toBe('200.000000');
});

test('submitting a draft with zero lines is rejected by SubmitStockCount', function () {
    $count = StockCount::query()->create([
        'organization_id' => $this->organization->id,
        'location_id' => $this->location->id,
        'storage_location_id' => $this->storageLocation->id,
        'number' => 'SC-EMPTY-1',
        'status' => StockCountStatus::Draft,
        'counted_at' => null,
        'created_by' => $this->actor->id,
        'submitted_by' => null,
        'finalized_by' => null,
        'finalized_at' => null,
    ]);

    $this->post("/mobile/stock-counts/{$count->id}/submit")
        ->assertSessionHasErrors('lines');

    expect($count->refresh()->status)->toBe(StockCountStatus::Draft);
});

test('a draft belonging to another organization cannot be resumed or scanned into', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorageLocation = new StorageLocation;
    $otherStorageLocation->organization_id = $otherOrganization->id;
    $otherStorageLocation->location_id = $otherLocation->id;
    $otherStorageLocation->name = 'Other Storage';
    $otherStorageLocation->code = 'OTHER';
    $otherStorageLocation->active = true;
    $otherStorageLocation->save();

    $otherCount = StockCount::query()->create([
        'organization_id' => $otherOrganization->id,
        'location_id' => $otherLocation->id,
        'storage_location_id' => $otherStorageLocation->id,
        'number' => 'SC-OTHER-1',
        'status' => StockCountStatus::Draft,
        'counted_at' => null,
        'created_by' => $this->actor->id,
        'submitted_by' => null,
        'finalized_by' => null,
        'finalized_at' => null,
    ]);

    $this->get("/mobile/stock-counts/{$otherCount->id}/review")
        ->assertNotFound();

    $this->post('/mobile/stock-counts/lines', [
        'stock_count_id' => $otherCount->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '3',
    ])->assertSessionHasErrors('stock_count_id');
});

test('a user without CountsCreate permission is forbidden from adding a line', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $viewer->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $this->actingAs($viewer)->withSession([
        'active_organization_id' => $this->organization->id,
        'mobile_location_by_org' => [$this->organization->id => $this->location->id],
    ])->post('/mobile/stock-counts/lines', [
        'storage_location_id' => $this->storageLocation->id,
        'inventory_item_id' => $this->item->id,
        'unit_id' => $this->baseUnit->id,
        'quantity' => '1',
    ])->assertForbidden();
});
