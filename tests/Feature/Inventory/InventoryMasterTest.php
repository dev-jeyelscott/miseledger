<?php

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrganizationRole;
use App\Enums\StockMovementType;
use App\Models\InventoryBrand;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can create a unit of measure', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.units.store'), [
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'active' => true,
        ])
        ->assertRedirect(route('inventory.units.index'));

    $this->assertDatabaseHas('units_of_measure', [
        'organization_id' => $organization->id,
        'name' => 'Kilogram',
        'symbol' => 'kg',
        'active' => true,
    ]);
});

test('an auditor can view inventory but cannot modify it', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.items.index'))
        ->assertOk();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.units.store'), [
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('units_of_measure', 0);
});

test('inventory index only exposes the active organization items', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $ownUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

    $otherUnit = UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Piece',
            'symbol' => 'pc',
        ]);

    $ownItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $ownUnit->id,
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
        ]);

    InventoryItem::factory()
        ->for($otherOrganization)
        ->create([
            'base_unit_of_measure_id' => $otherUnit->id,
            'name' => 'Other tenant item',
            'sku' => 'OTHER-001',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.items.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/items/index')
                ->has('items', 1)
                ->where('items.0.id', $ownItem->id)
                ->where('items.0.name', 'Flour'),
        );
});

test('an inventory item cannot use another organizations base unit', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $otherUnit = UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
            'base_unit_of_measure_id' => $otherUnit->id,
            'active' => true,
        ])
        ->assertSessionHasErrors(
            'base_unit_of_measure_id',
        );

    $this->assertDatabaseCount('inventory_items', 0);
});

test('sku is unique inside an organization but reusable by another organization', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $unit = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'sku' => 'FLOUR-001',
        ]);

    $otherUnit = UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create();

    InventoryItem::factory()
        ->for($otherOrganization)
        ->create([
            'base_unit_of_measure_id' => $otherUnit->id,
            'sku' => 'FLOUR-001',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Duplicate flour',
            'sku' => 'flour-001',
            'base_unit_of_measure_id' => $unit->id,
            'active' => true,
        ])
        ->assertSessionHasErrors('sku');
});

test('an alternate unit stores an exact fixed precision base quantity', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $gram = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Gram',
            'symbol' => 'g',
        ]);

    $kilogram = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Kilogram',
            'symbol' => 'kg',
        ]);

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $gram->id,
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(
            route('inventory.items.units.store', $item),
            [
                'unit_of_measure_id' => $kilogram->id,
                'quantity_in_base_unit' => '1000.000000',
                'active' => true,
            ],
        )
        ->assertRedirect(
            route('inventory.items.edit', $item),
        );

    $conversion = InventoryItemUnit::query()->sole();

    expect($conversion->quantity_in_base_unit)
        ->toBe('1000.000000')
        ->and($conversion->unit_of_measure_id)
        ->toBe($kilogram->id);
});

test('the base unit cannot also be configured as an alternate unit', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $gram = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $gram->id,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(
            route('inventory.items.units.store', $item),
            [
                'unit_of_measure_id' => $gram->id,
                'quantity_in_base_unit' => '1.000000',
                'active' => true,
            ],
        )
        ->assertSessionHasErrors('unit_of_measure_id');

    $this->assertDatabaseCount('inventory_item_units', 0);
});

test('an explicit alternate unit may map across dimensions to the item base unit', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $milliliter = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Milliliter',
            'symbol' => 'ml',
            'dimension' => 'volume',
            'active' => true,
        ]);

    $bottle = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Bottle',
            'symbol' => 'bottle',
            'dimension' => 'count',
            'active' => true,
        ]);

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $milliliter->id,
            'name' => 'Cooking Oil',
            'sku' => 'OIL-001',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(
            route('inventory.items.units.store', $item),
            [
                'unit_of_measure_id' => $bottle->id,
                'quantity_in_base_unit' => '1000.000000',
                'active' => true,
            ],
        )
        ->assertRedirect(
            route('inventory.items.edit', $item),
        );

    $conversion = InventoryItemUnit::query()->sole();

    expect($conversion->inventory_item_id)
        ->toBe($item->id)
        ->and($conversion->unit_of_measure_id)
        ->toBe($bottle->id)
        ->and($conversion->quantity_in_base_unit)
        ->toBe('1000.000000')
        ->and($conversion->active)
        ->toBeTrue();
});

test('an item base unit cannot change after alternate units exist', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $gram = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $kilogram = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $piece = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $gram->id,
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
        ]);

    InventoryItemUnit::factory()
        ->for($item)
        ->create([
            'unit_of_measure_id' => $kilogram->id,
            'quantity_in_base_unit' => '1000.000000',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(
            route('inventory.items.update', $item),
            [
                'name' => 'Flour',
                'sku' => 'FLOUR-001',
                'base_unit_of_measure_id' => $piece->id,
                'active' => true,
            ],
        )
        ->assertSessionHasErrors(
            'base_unit_of_measure_id',
        );

    expect($item->refresh()->base_unit_of_measure_id)
        ->toBe($gram->id);
});

test('an item base unit can change before any stock movement exists', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $gram = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $piece = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $gram->id,
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(
            route('inventory.items.update', $item),
            [
                'name' => 'Flour',
                'sku' => 'FLOUR-001',
                'base_unit_of_measure_id' => $piece->id,
                'active' => true,
            ],
        )
        ->assertRedirect();

    expect($item->refresh()->base_unit_of_measure_id)
        ->toBe($piece->id);
});

test('an item base unit cannot change after a stock movement exists', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $gram = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $piece = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $gram->id,
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
        ]);

    $location = Location::factory()
        ->for($organization)
        ->create();

    $storageLocation = new StorageLocation;
    $storageLocation->organization_id = $organization->id;
    $storageLocation->location_id = $location->id;
    $storageLocation->name = 'Main Storage';
    $storageLocation->code = 'MAIN';
    $storageLocation->active = true;
    $storageLocation->save();

    app(RecordStockMovement::class)->handle(
        organization: $organization,
        location: $location,
        storageLocation: $storageLocation,
        inventoryItem: $item,
        type: StockMovementType::OpeningBalance,
        baseQuantity: '10.000000',
        baseUnitOfMeasure: $gram,
        referenceType: 'opening_balance',
        referenceId: 1,
        occurredAt: now(),
        inboundUnitCost: '1.0000',
    );

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(
            route('inventory.items.update', $item),
            [
                'name' => 'Flour',
                'sku' => 'FLOUR-001',
                'base_unit_of_measure_id' => $piece->id,
                'active' => true,
            ],
        )
        ->assertSessionHasErrors(
            'base_unit_of_measure_id',
        );

    expect($item->refresh()->base_unit_of_measure_id)
        ->toBe($gram->id);
});

test('a referenced unit of measure cannot be deactivated', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $gram = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Gram',
            'symbol' => 'g',
            'dimension' => 'weight',
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $gram->id,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(
            route('inventory.units.update', $gram),
            [
                'name' => 'Gram',
                'symbol' => 'g',
                'dimension' => 'weight',
                'active' => false,
            ],
        )
        ->assertSessionHasErrors('active');

    expect($gram->refresh()->active)->toBeTrue();
});

test('cross organization inventory item editing is not exposed', function () {
    $user = User::factory()->create();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $otherUnit = UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create();

    $otherItem = InventoryItem::factory()
        ->for($otherOrganization)
        ->create([
            'base_unit_of_measure_id' => $otherUnit->id,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.items.edit', $otherItem))
        ->assertNotFound();
});

test('an inventory item remains valid without brand or item-master metadata', function () {
    $organization = Organization::factory()->create();
    $unit = UnitOfMeasure::factory()->for($organization)->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create(['base_unit_of_measure_id' => $unit->id]);

    expect($item->inventory_brand_id)->toBeNull()
        ->and($item->model_number)->toBeNull()
        ->and($item->manufacturer_part_number)->toBeNull()
        ->and($item->description)->toBeNull();
});

test('an owner can assign an active brand and item-master metadata to an item', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();
    $brand = InventoryBrand::factory()->for($organization)->create();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $brand->id,
            'model_number' => 'MDL-1',
            'manufacturer_part_number' => 'MPN-1',
            'description' => 'All-purpose baking flour.',
            'active' => true,
        ])
        ->assertRedirect();

    $item = InventoryItem::query()->sole();

    expect($item->inventory_brand_id)->toBe($brand->id)
        ->and($item->model_number)->toBe('MDL-1')
        ->and($item->manufacturer_part_number)->toBe('MPN-1')
        ->and($item->description)->toBe('All-purpose baking flour.');
});

test('an inventory item rejects a brand owned by another organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();
    $otherBrand = InventoryBrand::factory()->for($otherOrganization)->create();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $otherBrand->id,
            'active' => true,
        ])
        ->assertSessionHasErrors('inventory_brand_id');

    $this->assertDatabaseCount('inventory_items', 0);
});

test('an assigned inactive brand can be retained but not newly assigned', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();
    $brand = InventoryBrand::factory()->for($organization)->create();
    $assignedItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $brand->id,
        ]);
    $otherItem = InventoryItem::factory()
        ->for($organization)
        ->create(['base_unit_of_measure_id' => $unit->id]);

    $brand->update(['active' => false]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.items.edit', $assignedItem))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/items/edit')
                ->has('brands', 1)
                ->where('brands.0.id', $brand->id)
                ->where('brands.0.active', false),
        );

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->put(route('inventory.items.update', $assignedItem), [
            'name' => 'Updated item name',
            'sku' => $assignedItem->sku,
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $brand->id,
            'active' => true,
        ])
        ->assertRedirect(route('inventory.items.edit', $assignedItem));

    expect($assignedItem->refresh()->inventory_brand_id)
        ->toBe($brand->id)
        ->and($assignedItem->name)->toBe('Updated item name');

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->put(route('inventory.items.update', $otherItem), [
            'name' => $otherItem->name,
            'sku' => $otherItem->sku,
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $brand->id,
            'active' => true,
        ])
        ->assertSessionHasErrors('inventory_brand_id');

    expect($otherItem->refresh()->inventory_brand_id)->toBeNull();
});

test('an auditor cannot assign a brand to an inventory item', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Auditor]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();
    $brand = InventoryBrand::factory()->for($organization)->create();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $brand->id,
            'active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('inventory_items', 0);
});
