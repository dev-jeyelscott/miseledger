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

function makeStorageLocationForLowStockTest(
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

    $this->storageLocation = makeStorageLocationForLowStockTest(
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

    $this->zeroItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'inventory_category_id' => $this->category->id,
        'name' => 'Zero Stock Item',
        'sku' => 'ZERO-STOCK',
        'active' => true,
    ]);

    $this->negativeItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'inventory_category_id' => $this->category->id,
        'name' => 'Negative Stock Item',
        'sku' => 'NEGATIVE-STOCK',
        'active' => true,
    ]);

    $this->inStockItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'inventory_category_id' => $this->category->id,
        'name' => 'In Stock Item',
        'sku' => 'IN-STOCK',
        'active' => true,
    ]);

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->zeroItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '5',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: $this->zeroItem->id,
        occurredAt: now(),
        idempotencyKey: "low-stock-test:zero:opening:{$this->zeroItem->id}",
        inboundUnitCost: '4.0000',
    );

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->zeroItem,
        type: StockMovementType::CountAdjustment,
        baseQuantity: '-5',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'stock_count_line',
        referenceId: $this->zeroItem->id,
        occurredAt: now(),
        idempotencyKey: "low-stock-test:zero:adjustment:{$this->zeroItem->id}",
    );

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->negativeItem,
        type: StockMovementType::CountAdjustment,
        baseQuantity: '-3',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'stock_count_line',
        referenceId: $this->negativeItem->id,
        occurredAt: now(),
        idempotencyKey: "low-stock-test:negative:{$this->negativeItem->id}",
    );

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->inStockItem,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '10',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'opening_balance',
        referenceId: $this->inStockItem->id,
        occurredAt: now(),
        idempotencyKey: "low-stock-test:in-stock:{$this->inStockItem->id}",
        inboundUnitCost: '4.0000',
    );

    $this->manager = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->manager->id,
        'role' => OrganizationRole::Manager,
    ]);
});

test('report lists only balances at zero or negative quantity', function () {
    $url = route('inventory.low-stock.index');

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/low-stock')
                ->has('rows', 2)
                ->where('rows.0.itemId', $this->negativeItem->id)
                ->where('rows.0.quantityOnHand', '-3.000000')
                ->where('rows.1.itemId', $this->zeroItem->id)
                ->where('rows.1.quantityOnHand', '0.000000'),
        );
});

test('report location filter excludes balances from other locations', function () {
    $otherLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForLowStockTest(
        $this->organization,
        $otherLocation,
        'B',
    );

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $otherLocation,
        storageLocation: $otherStorage,
        inventoryItem: $this->zeroItem,
        type: StockMovementType::CountAdjustment,
        baseQuantity: '-1',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'stock_count_line',
        referenceId: $this->zeroItem->id,
        occurredAt: now(),
        idempotencyKey: "low-stock-test:other-location:{$this->zeroItem->id}",
    );

    $url = route('inventory.low-stock.index', [
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
                ->component('inventory/low-stock')
                ->has('rows', 1)
                ->where('rows.0.locationId', $otherLocation->id)
                ->where('rows.0.quantityOnHand', '-1.000000'),
        );
});

test('report storage location filter narrows results within a restaurant location', function () {
    $otherStorage = makeStorageLocationForLowStockTest(
        $this->organization,
        $this->location,
        'C',
    );

    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $otherStorage,
        inventoryItem: $this->zeroItem,
        type: StockMovementType::CountAdjustment,
        baseQuantity: '-2',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'stock_count_line',
        referenceId: $this->zeroItem->id,
        occurredAt: now(),
        idempotencyKey: "low-stock-test:other-storage:{$this->zeroItem->id}",
    );

    $url = route('inventory.low-stock.index', [
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
                ->component('inventory/low-stock')
                ->has('rows', 1)
                ->where(
                    'rows.0.storageLocationId',
                    $otherStorage->id,
                ),
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
        type: StockMovementType::CountAdjustment,
        baseQuantity: '-4',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'stock_count_line',
        referenceId: $otherItem->id,
        occurredAt: now(),
        idempotencyKey: "low-stock-test:other-item:{$otherItem->id}",
    );

    $url = route('inventory.low-stock.index', [
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
                ->component('inventory/low-stock')
                ->has('rows', 1)
                ->where('rows.0.itemId', $otherItem->id),
        );
});

test('report item filter narrows results to the requested inventory item', function () {
    $url = route('inventory.low-stock.index', [
        'inventory_item_id' => $this->negativeItem->id,
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
                ->component('inventory/low-stock')
                ->has('rows', 1)
                ->where('rows.0.itemId', $this->negativeItem->id),
        );
});

test('report is tenant isolated across organizations', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForLowStockTest(
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
        type: StockMovementType::CountAdjustment,
        baseQuantity: '-6',
        baseUnitOfMeasure: $otherUnit,
        referenceType: 'stock_count_line',
        referenceId: $otherItem->id,
        occurredAt: now(),
        idempotencyKey: "low-stock-test:tenant:{$otherItem->id}",
    );

    $url = route('inventory.low-stock.index');

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/low-stock')
                ->has('rows', 2),
        );
});

test('report requires reports.view permission', function () {
    $unprivileged = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $unprivileged->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $url = route('inventory.low-stock.index');

    $this
        ->actingAs($unprivileged)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertForbidden();
});
