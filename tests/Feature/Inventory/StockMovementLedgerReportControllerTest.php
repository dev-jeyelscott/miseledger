<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeStorageLocationForLedgerTest(
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

    $this->storageLocation = makeStorageLocationForLedgerTest(
        $this->organization,
        $this->location,
        'A',
    );

    $this->unit = UnitOfMeasure::factory()->create([
        'organization_id' => $this->organization->id,
        'dimension' => 'weight',
    ]);

    $this->item = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
        'name' => 'Ledger Test Item',
        'sku' => 'LEDGER-TEST',
        'active' => true,
    ]);

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

    $this->movement = app(RecordStockMovement::class)->handle(
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
        actor: $this->manager,
        idempotencyKey: "ledger-test:opening:{$this->item->id}",
        inboundUnitCost: '4.0000',
    );
});

test('report hides cost fields from members without cost visibility', function () {
    $url = route('inventory.stock-movements.index');

    $this
        ->actingAs($this->staff)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-movement-ledger')
                ->has('rows.data', 1)
                ->where('rows.data.0.unitCost', null)
                ->where('rows.data.0.totalCost', null)
                ->where('canViewCosts', false),
        );
});

test('report shows cost, source, and actor fields to members with cost visibility', function () {
    $url = route('inventory.stock-movements.index');

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-movement-ledger')
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $this->movement->id)
                ->where('rows.data.0.quantity', '10.000000')
                ->where('rows.data.0.unitCost', '4.0000')
                ->where('rows.data.0.totalCost', '40.0000')
                ->where('rows.data.0.referenceType', 'opening_balance')
                ->where('rows.data.0.referenceId', $this->item->id)
                ->where('rows.data.0.actorName', $this->manager->name)
                ->where('canViewCosts', true),
        );
});

test('report location filter excludes movements from other locations', function () {
    $otherLocation = Location::factory()->create([
        'organization_id' => $this->organization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForLedgerTest(
        $this->organization,
        $otherLocation,
        'B',
    );

    $otherMovement = app(RecordStockMovement::class)->handle(
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
        idempotencyKey: "ledger-test:other-location:{$this->item->id}",
        inboundUnitCost: '2.0000',
    );

    $url = route('inventory.stock-movements.index', [
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
                ->component('inventory/stock-movement-ledger')
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $otherMovement->id)
                ->where('rows.data.0.locationId', $otherLocation->id),
        );
});

test('report storage location filter narrows results within a restaurant location', function () {
    $otherStorage = makeStorageLocationForLedgerTest(
        $this->organization,
        $this->location,
        'C',
    );

    $otherMovement = app(RecordStockMovement::class)->handle(
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
        idempotencyKey: "ledger-test:other-storage:{$this->item->id}",
        inboundUnitCost: '1.0000',
    );

    $url = route('inventory.stock-movements.index', [
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
                ->component('inventory/stock-movement-ledger')
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $otherMovement->id)
                ->where(
                    'rows.data.0.storageLocationId',
                    $otherStorage->id,
                ),
        );
});

test('report item filter narrows results to the requested inventory item', function () {
    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $this->organization->id,
        'base_unit_of_measure_id' => $this->unit->id,
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
        idempotencyKey: "ledger-test:other-item:{$otherItem->id}",
        inboundUnitCost: '1.0000',
    );

    $url = route('inventory.stock-movements.index', [
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
                ->component('inventory/stock-movement-ledger')
                ->has('rows.data', 1)
                ->where('rows.data.0.itemId', $this->item->id),
        );
});

test('report type filter narrows results to the requested movement type', function () {
    app(RecordStockMovement::class)->handle(
        organization: $this->organization,
        location: $this->location,
        storageLocation: $this->storageLocation,
        inventoryItem: $this->item,
        type: StockMovementType::Waste,
        baseQuantity: '-2',
        baseUnitOfMeasure: $this->unit,
        referenceType: 'waste_record',
        referenceId: $this->item->id,
        occurredAt: now(),
        idempotencyKey: "ledger-test:waste:{$this->item->id}",
    );

    $url = route('inventory.stock-movements.index', [
        'type' => StockMovementType::Waste->value,
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
                ->component('inventory/stock-movement-ledger')
                ->has('rows.data', 1)
                ->where(
                    'rows.data.0.type',
                    StockMovementType::Waste->value,
                ),
        );
});

test('report date range filters exclude movements outside the range', function () {
    $url = route('inventory.stock-movements.index', [
        'from' => now()->addDay()->toDateString(),
        'to' => now()->addDays(2)->toDateString(),
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
                ->component('inventory/stock-movement-ledger')
                ->has('rows.data', 0),
        );
});

test('report is tenant isolated across organizations', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForLedgerTest(
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
        idempotencyKey: "ledger-test:tenant:{$otherItem->id}",
        inboundUnitCost: '9.0000',
    );

    $url = route('inventory.stock-movements.index');

    $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get($url)
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/stock-movement-ledger')
                ->has('rows.data', 1)
                ->where('rows.data.0.itemId', $this->item->id),
        );
});

test('report requires reports.view permission', function () {
    $unprivileged = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $unprivileged->id,
        'role' => OrganizationRole::KitchenStaff,
    ]);

    $url = route('inventory.stock-movements.index');

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
        ->get(route('inventory.stock-movements.export'))
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
        ->get(route('inventory.stock-movements.export'))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain('4.0000');
    expect($content)->toContain($this->manager->name);
});

test('export is tenant isolated across organizations', function () {
    $otherOrganization = Organization::factory()->create();

    $otherLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);

    $otherStorage = makeStorageLocationForLedgerTest(
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
        'name' => 'Other Tenant Ledger Item',
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
        idempotencyKey: "ledger-export-test:tenant:{$otherItem->id}",
        inboundUnitCost: '9.0000',
    );

    $content = $this
        ->actingAs($this->manager)
        ->withSession([
            'active_organization_id' => $this->organization->id,
        ])
        ->get(route('inventory.stock-movements.export'))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain($this->item->name);
    expect($content)->not->toContain('Other Tenant Ledger Item');
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
        ->get(route('inventory.stock-movements.export'))
        ->assertForbidden();
});
