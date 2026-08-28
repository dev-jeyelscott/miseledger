<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryBrand;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an inventory item can update and clear core product metadata', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $unit = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $brand = InventoryBrand::factory()
        ->for($organization)
        ->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.items.update', $item), [
            'name' => $item->name,
            'sku' => $item->sku,
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $brand->id,
            'model_number' => 'MODEL-100',
            'manufacturer_part_number' => 'MPN-100',
            'description' => 'Updated inventory master description.',
            'type' => $item->type->value,
            'yield_percentage' => $item->yield_percentage,
            'active' => true,
        ])
        ->assertRedirect(route('inventory.items.edit', $item));

    $item->refresh();

    expect($item->inventory_brand_id)
        ->toBe($brand->id)
        ->and($item->model_number)
        ->toBe('MODEL-100')
        ->and($item->manufacturer_part_number)
        ->toBe('MPN-100')
        ->and($item->description)
        ->toBe('Updated inventory master description.');

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.items.update', $item), [
            'name' => $item->name,
            'sku' => $item->sku,
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => null,
            'model_number' => null,
            'manufacturer_part_number' => null,
            'description' => null,
            'type' => $item->type->value,
            'yield_percentage' => $item->yield_percentage,
            'active' => true,
        ])
        ->assertRedirect(route('inventory.items.edit', $item));

    $item->refresh();

    expect($item->inventory_brand_id)
        ->toBeNull()
        ->and($item->model_number)
        ->toBeNull()
        ->and($item->manufacturer_part_number)
        ->toBeNull()
        ->and($item->description)
        ->toBeNull();
});

test('core product metadata accepts values at the repository length limits', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $unit = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Boundary test item',
            'sku' => 'BOUNDARY-001',
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => null,
            'model_number' => str_repeat('M', 120),
            'manufacturer_part_number' => str_repeat('P', 120),
            'description' => str_repeat('D', 10000),
            'type' => 'ingredient',
            'yield_percentage' => '100.00',
            'active' => true,
        ])
        ->assertRedirect();

    $item = InventoryItem::query()->sole();

    expect(strlen((string) $item->model_number))
        ->toBe(120)
        ->and(strlen((string) $item->manufacturer_part_number))
        ->toBe(120)
        ->and(strlen((string) $item->description))
        ->toBe(10000);
});

test(
    'core product metadata rejects values beyond repository length limits',
    function (string $field, string $value) {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        OrganizationMembership::factory()
            ->for($organization)
            ->for($user)
            ->create([
                'role' => OrganizationRole::Owner,
            ]);

        $unit = UnitOfMeasure::factory()
            ->for($organization)
            ->create();

        $payload = [
            'name' => 'Validation test item',
            'sku' => 'VALIDATION-001',
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => null,
            'model_number' => null,
            'manufacturer_part_number' => null,
            'description' => null,
            'type' => 'ingredient',
            'yield_percentage' => '100.00',
            'active' => true,
        ];

        $payload[$field] = $value;

        $this->withSession([
            'active_organization_id' => $organization->id,
        ])
            ->actingAs($user)
            ->post(route('inventory.items.store'), $payload)
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('inventory_items', 0);
    },
)->with([
    'model number longer than 120 characters' => [
        'model_number',
        str_repeat('M', 121),
    ],
    'manufacturer part number longer than 120 characters' => [
        'manufacturer_part_number',
        str_repeat('P', 121),
    ],
    'description longer than 10000 characters' => [
        'description',
        str_repeat('D', 10001),
    ],
]);

test('inventory item forms expose organization safe brands and metadata props', function () {
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

    $activeBrand = InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Active Brand',
            'active' => true,
        ]);

    InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Inactive Brand',
            'active' => false,
        ]);

    InventoryBrand::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Other Organization Brand',
            'active' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.items.create'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/items/create')
                ->has('brands', 1)
                ->where('brands.0.id', $activeBrand->id)
                ->where('brands.0.name', 'Active Brand')
                ->where('brands.0.active', true),
        );

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $activeBrand->id,
            'model_number' => 'MODEL-500',
            'manufacturer_part_number' => 'MPN-500',
            'description' => 'Metadata exposed through Inertia.',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.items.edit', $item))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/items/edit')
                ->where('item.id', $item->id)
                ->where('item.modelNumber', 'MODEL-500')
                ->where(
                    'item.manufacturerPartNumber',
                    'MPN-500',
                )
                ->where(
                    'item.description',
                    'Metadata exposed through Inertia.',
                )
                ->where(
                    'item.inventoryBrand.id',
                    $activeBrand->id,
                )
                ->where(
                    'item.inventoryBrand.name',
                    'Active Brand',
                ),
        );
});
