<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\OrganizationMembership;
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
