<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeStorageLocationForStockOnHandTest(
    Organization $organization,
    Location $location,
    string $code,
): StorageLocation {
    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = "Storage {$code}";
    $storageLocation->code = $code;
    $storageLocation->active = true;
    $storageLocation->save();

    return $storageLocation;
}

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'currency' => 'PHP',
    ]);

    $this->location = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->storageLocation = makeStorageLocationForStockOnHandTest(
        $this->organization,
        $this->location,
        'A',
    );

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'dimension' => 'weight',
    ]);

    $this->category = InventoryCategory::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'inventory_category_id' => $this->category->id,
        'name' => 'Report Test Item',
        'sku' => 'REPORT-TEST',
        'active' => true,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '10',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: $this->item->id,
        occurredAt: now(),
        idempotencyKey: "stock-on-hand-test:opening:{$this->item->id}",
        inboundUnitCost: '4.0000',
    );

    $this->staff = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->staff->id,
        'role' => OrganizationRole::InventoryStaff,
    ]);

    $this->manager = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->manager->id,
        'role' => OrganizationRole::Manager,
    ]);
});

test('report hides cost fields from members without cost visibility', function () {
    $url = route('inventory.stock-on-hand.index');

    $this
        ->actingAs($this->staff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-on-hand')
                ->has('rows', 1)
                ->where('rows.0.quantityOnHand', '10.000000')
                ->where('rows.0.baseUnitSymbol', $this->unit->symbol)
                ->where('rows.0.averageUnitCost', null)
                ->where('rows.0.inventoryValue', null)
                ->where('canViewCosts', false),
        );
});

test('report shows cost fields to members with cost visibility', function () {
    $url = route('inventory.stock-on-hand.index');

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-on-hand')
                ->has('rows', 1)
                ->where('rows.0.averageUnitCost', '4.0000')
                ->where('rows.0.inventoryValue', '40.0000')
                ->where('canViewCosts', true),
        );
});

test('report location filter excludes balances from other locations', function () {
    $otherLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForStockOnHandTest(
        $this->organization,
        $otherLocation,
        'B',
    );

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $otherLocation,
        storageLocation: $otherStorage,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '5',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: $this->item->id,
        occurredAt: now(),
        idempotencyKey: "stock-on-hand-test:other-location:{$this->item->id}",
        inboundUnitCost: '2.0000',
    );

    $url = route('inventory.stock-on-hand.index', [
        'location_id' => $otherLocation->id,
    ]);

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-on-hand')
                ->has('rows', 1)
                ->where('rows.0.locationId', $otherLocation->id)
                ->where('rows.0.quantityOnHand', '5.000000'),
        );
});

test('report storage location filter narrows results within a restaurant location', function () {
    $otherStorage = makeStorageLocationForStockOnHandTest(
        $this->organization,
        $this->location,
        'C',
    );

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $otherStorage,
        inventoryItem: $this->item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '3',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: $this->item->id,
        occurredAt: now(),
        idempotencyKey: "stock-on-hand-test:other-storage:{$this->item->id}",
        inboundUnitCost: '1.0000',
    );

    $url = route('inventory.stock-on-hand.index', [
        'storage_location_id' => $otherStorage->id,
    ]);

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-on-hand')
                ->has('rows', 1)
                ->where(
                    'rows.0.storageLocationId',
                    $otherStorage->id,
                )
                ->where('rows.0.quantityOnHand', '3.000000'),
        );
});

test('report category filter excludes balances for items outside the category', function () {
    $otherCategory = InventoryCategory::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'inventory_category_id' => $otherCategory->id,
        'active' => true,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $otherItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '7',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: $otherItem->id,
        occurredAt: now(),
        idempotencyKey: "stock-on-hand-test:other-item:{$otherItem->id}",
        inboundUnitCost: '1.0000',
    );

    $url = route('inventory.stock-on-hand.index', [
        'inventory_category_id' => $otherCategory->id,
    ]);

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-on-hand')
                ->has('rows', 1)
                ->where('rows.0.itemId', $otherItem->id),
        );
});

test('report item filter narrows results to the requested inventory item', function () {
    $url = route('inventory.stock-on-hand.index', [
        'inventory_item_id' => $this->item->id,
    ]);

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-on-hand')
                ->has('rows', 1)
                ->where('rows.0.itemId', $this->item->id),
        );
});

test('report is tenant isolated across organizations', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForStockOnHandTest(
        $otherOrganization,
        $otherLocation,
        'X',
    );

    $otherUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
        'dimension' => 'weight',
    ]);

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $otherOrganization->id,
        'base_unit_of_measure_id' => $otherUnit->id,
        'active' => true,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $otherOrganization,
        location: $otherLocation,
        storageLocation: $otherStorage,
        inventoryItem: $otherItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '99',
        baseUnitOfMeasure: $otherUnit,
        referenceType: 'opening_balance',
        referenceId: $otherItem->id,
        occurredAt: now(),
        idempotencyKey: "stock-on-hand-test:tenant:{$otherItem->id}",
        inboundUnitCost: '9.0000',
    );

    $url = route('inventory.stock-on-hand.index');

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-on-hand')
                ->has('rows', 1)
                ->where('rows.0.itemId', $this->item->id),
        );
});

test('report requires reports.view permission', function () {
    $unprivileged = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $unprivileged->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $url = route('inventory.stock-on-hand.index');

    $this
        ->actingAs($unprivileged)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertForbidden();
});

test('export streams a CSV with cost fields hidden from members without cost visibility', function () {
    $url = route('inventory.stock-on-hand.export');

    $response = $this
        ->actingAs($this->staff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk();

    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)->toContain('Quantity on Hand');
    expect($content)->toContain($this->item->name);
    expect($content)->not->toContain('4.0000');
});

test('export streams a CSV with cost fields to members with cost visibility', function () {
    $url = route('inventory.stock-on-hand.export');

    $response = $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk();

    $content = $response->streamedContent();

    expect($content)->toContain($this->item->name);
    expect($content)->toContain('4.0000');
});

test('export is tenant isolated across organizations', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForStockOnHandTest(
        $otherOrganization,
        $otherLocation,
        'X',
    );

    $otherUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
        'dimension' => 'weight',
    ]);

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $otherOrganization->id,
        'base_unit_of_measure_id' => $otherUnit->id,
        'active' => true,
        'name' => 'Other Tenant Item',
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $otherOrganization,
        location: $otherLocation,
        storageLocation: $otherStorage,
        inventoryItem: $otherItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '99',
        baseUnitOfMeasure: $otherUnit,
        referenceType: 'opening_balance',
        referenceId: $otherItem->id,
        occurredAt: now(),
        idempotencyKey: "stock-on-hand-export-test:tenant:{$otherItem->id}",
        inboundUnitCost: '9.0000',
    );

    $url = route('inventory.stock-on-hand.export');

    $response = $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk();

    $content = $response->streamedContent();

    expect($content)->toContain($this->item->name);
    expect($content)->not->toContain('Other Tenant Item');
});

test('export requires reports.view permission', function () {
    $unprivileged = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $unprivileged->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $url = route('inventory.stock-on-hand.export');

    $this
        ->actingAs($unprivileged)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertForbidden();
});
