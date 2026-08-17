<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StockBalance;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeStorageLocationForValuationTest(
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

    $this->storageLocation = makeStorageLocationForValuationTest(
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
        'name' => 'Valuation Test Item',
        'sku' => 'VALUATION-TEST',
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
        idempotencyKey: "valuation-test:opening:{$this->item->id}",
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

test('report hides cost fields and totals from members without cost visibility', function () {
    $url = route('inventory.valuation.index');

    $this
        ->actingAs($this->staff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/valuation')
                ->has('rows', 1)
                ->where('rows.0.quantityOnHand', '10.000000')
                ->where('rows.0.averageUnitCost', null)
                ->where('rows.0.inventoryValue', null)
                ->where('locationTotals', [])
                ->where('categoryTotals', [])
                ->where('grandTotal', null)
                ->where('canViewCosts', false),
        );
});

test('report shows cost fields and aggregated totals to members with cost visibility', function () {
    $url = route('inventory.valuation.index');

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/valuation')
                ->has('rows', 1)
                ->where('rows.0.averageUnitCost', '4.0000')
                ->where('rows.0.inventoryValue', '40.0000')
                ->has('locationTotals', 1)
                ->where('locationTotals.0.locationId', $this->location->id)
                ->where('locationTotals.0.value', '40.0000')
                ->has('categoryTotals', 1)
                ->where('categoryTotals.0.categoryId', $this->category->id)
                ->where('categoryTotals.0.value', '40.0000')
                ->where('grandTotal', '40.0000')
                ->where('canViewCosts', true),
        );
});

test('current quantity times average cost reconciles to the stock balance value', function () {
    $balance = StockBalance::query()
        ->where('organization_id', $this->organization->id)
        ->where('inventory_item_id', $this->item->id)
        ->firstOrFail();

    $expected = bcmul(
        $balance->quantity_on_hand,
        $balance->average_unit_cost,
        4,
    );

    expect($expected)->toBe($balance->inventory_value);

    $url = route('inventory.valuation.index');

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/valuation')
                ->where('rows.0.inventoryValue', $balance->inventory_value),
        );
});

test('report location filter excludes balances from other locations', function () {
    $otherLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForValuationTest(
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
        idempotencyKey: "valuation-test:other-location:{$this->item->id}",
        inboundUnitCost: '2.0000',
    );

    $url = route('inventory.valuation.index', [
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
                ->component('inventory/valuation')
                ->has('rows', 1)
                ->where('rows.0.locationId', $otherLocation->id)
                ->where('rows.0.quantityOnHand', '5.000000'),
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
        idempotencyKey: "valuation-test:other-item:{$otherItem->id}",
        inboundUnitCost: '1.0000',
    );

    $url = route('inventory.valuation.index', [
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
                ->component('inventory/valuation')
                ->has('rows', 1)
                ->where('rows.0.itemId', $otherItem->id),
        );
});

test('report is tenant isolated across organizations', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForValuationTest(
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
        idempotencyKey: "valuation-test:tenant:{$otherItem->id}",
        inboundUnitCost: '9.0000',
    );

    $url = route('inventory.valuation.index');

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/valuation')
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

    $url = route('inventory.valuation.index');

    $this
        ->actingAs($unprivileged)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertForbidden();
});

test('export hides cost fields from members without cost visibility', function () {
    $content = $this
        ->actingAs($this->staff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.valuation.export'))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain($this->item->name);
    expect($content)->not->toContain('4.0000');
});

test('export shows cost fields to members with cost visibility', function () {
    $content = $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.valuation.export'))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain('4.0000');
    expect($content)->toContain('40.0000');
});

test('export is tenant isolated across organizations', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForValuationTest(
        $otherOrganization,
        $otherLocation,
        'Y',
    );

    $otherUnit = UnitOfMeasure::factory()->create([
        'organization_id' => $otherOrganization->id,
        'dimension' => 'weight',
    ]);

    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $otherOrganization->id,
        'base_unit_of_measure_id' => $otherUnit->id,
        'active' => true,
        'name' => 'Other Tenant Valuation Item',
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
        idempotencyKey: "valuation-export-test:tenant:{$otherItem->id}",
        inboundUnitCost: '9.0000',
    );

    $content = $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.valuation.export'))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain($this->item->name);
    expect($content)->not->toContain('Other Tenant Valuation Item');
});

test('export requires reports.view permission', function () {
    $unprivileged = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $unprivileged->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $this
        ->actingAs($unprivileged)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.valuation.export'))
        ->assertForbidden();
});
