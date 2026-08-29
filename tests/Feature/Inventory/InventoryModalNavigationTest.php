<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create an authenticated inventory owner with one active base unit.
 *
 * @return array{User, Organization, UnitOfMeasure}
 */
function inventoryModalNavigationContext(): array
{
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
        ->create([
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'dimension' => 'weight',
            'active' => true,
        ]);

    return [$user, $organization, $unit];
}

test('inventory index exposes only active tenant units for modal item creation', function () {
    [$user, $organization, $unit] = inventoryModalNavigationContext();

    UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Inactive Piece',
            'symbol' => 'pc',
            'dimension' => 'count',
            'active' => false,
        ]);

    $otherOrganization = Organization::factory()->create();

    UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Other Tenant Liter',
            'symbol' => 'l',
            'dimension' => 'volume',
            'active' => true,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->where('canManage', true)
                ->has('createUnitOptions', 1)
                ->where('createUnitOptions.0.id', $unit->id)
                ->where('createUnitOptions.0.name', 'Kilogram'),
        );
});

test('modal item creation returns to the exact inventory index context', function () {
    [$user, $organization, $unit] = inventoryModalNavigationContext();

    $category = InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Pantry',
            'active' => true,
        ]);

    $indexUrl = route('inventory.items.index', [
        'search' => 'flour',
        'category' => $category->id,
        'type' => 'ingredient',
        'status' => 'active',
        'sort' => 'sku',
        'direction' => 'desc',
        'page' => 2,
    ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->from($indexUrl)
        ->post(route('inventory.items.store'), [
            '_modal' => '1',
            'name' => 'All-purpose Flour',
            'sku' => 'FLOUR-001',
            'type' => 'ingredient',
            'inventory_category_id' => $category->id,
            'yield_percentage' => '100.00',
            'base_unit_of_measure_id' => $unit->id,
            'active' => true,
        ])
        ->assertRedirect($indexUrl);

    $this->assertDatabaseHas('inventory_items', [
        'organization_id' => $organization->id,
        'name' => 'All-purpose Flour',
        'sku' => 'FLOUR-001',
        'inventory_category_id' => $category->id,
        'base_unit_of_measure_id' => $unit->id,
        'active' => true,
    ]);
});

test('standalone item creation keeps the existing edit redirect', function () {
    [$user, $organization, $unit] = inventoryModalNavigationContext();

    $response = $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Whole Milk',
            'sku' => 'MILK-001',
            'type' => 'ingredient',
            'inventory_category_id' => null,
            'yield_percentage' => '100.00',
            'base_unit_of_measure_id' => $unit->id,
            'active' => true,
        ]);

    $item = InventoryItem::query()
        ->where('organization_id', $organization->id)
        ->where('sku', 'MILK-001')
        ->sole();

    $response->assertRedirect(route('inventory.items.edit', $item));
});

test('modal category unit and conversion edits return to their current context', function () {
    [$user, $organization, $baseUnit] = inventoryModalNavigationContext();

    $category = InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Pantry',
            'active' => true,
        ]);

    $categoriesUrl = route('inventory.categories.index');

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->from($categoriesUrl)
        ->put(route('inventory.categories.update', $category), [
            '_modal' => '1',
            'name' => 'Dry Pantry',
            'active' => true,
        ])
        ->assertRedirect($categoriesUrl);

    expect($category->refresh()->name)->toBe('Dry Pantry');

    $editableUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Bottle',
            'symbol' => 'btl',
            'dimension' => 'count',
            'active' => true,
        ]);

    $unitsUrl = route('inventory.units.index');

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->from($unitsUrl)
        ->put(route('inventory.units.update', $editableUnit), [
            '_modal' => '1',
            'name' => 'Bottle Each',
            'symbol' => 'btl',
            'dimension' => 'count',
            'active' => true,
        ])
        ->assertRedirect($unitsUrl);

    expect($editableUnit->refresh()->name)->toBe('Bottle Each');

    $alternateUnit = UnitOfMeasure::factory()
        ->for($organization)
        ->create([
            'name' => 'Gram',
            'symbol' => 'g',
            'dimension' => 'weight',
            'active' => true,
        ]);

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $baseUnit->id,
            'name' => 'Flour',
            'sku' => 'FLOUR-002',
        ]);

    $conversion = InventoryItemUnit::factory()
        ->for($item)
        ->create([
            'unit_of_measure_id' => $alternateUnit->id,
            'quantity_in_base_unit' => '0.001000',
            'active' => true,
        ]);

    $itemEditUrl = route('inventory.items.edit', $item);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->from($itemEditUrl)
        ->put(
            route('inventory.items.units.update', [
                'inventoryItem' => $item,
                'inventoryItemUnit' => $conversion,
            ]),
            [
                '_modal' => '1',
                'quantity_in_base_unit' => '0.002000',
                'active' => true,
            ],
        )
        ->assertRedirect($itemEditUrl);

    expect($conversion->refresh()->quantity_in_base_unit)
        ->toBe('0.002000');
});

test('inventory modal and return controls use shared navigation primitives', function () {
    $modalPages = [
        'js/pages/inventory/items/index.tsx' => 'CreateInventoryItemDialog',
        'js/pages/inventory/items/edit.tsx' => 'EditInventoryItemUnitDialog',
        'js/pages/inventory/categories/index.tsx' => 'EditInventoryCategoryDialog',
        'js/pages/inventory/units/index.tsx' => 'EditUnitOfMeasureDialog',
    ];

    foreach ($modalPages as $page => $dialogName) {
        $source = file_get_contents(resource_path($page));

        expect($source)
            ->toContain($dialogName)
            ->toContain('useGuardedDialog')
            ->toContain('name="_modal"');
    }

    $historyAwarePages = [
        'js/pages/inventory/items/create.tsx',
        'js/pages/inventory/items/unit-edit.tsx',
        'js/pages/inventory/categories/index.tsx',
        'js/pages/inventory/categories/edit.tsx',
        'js/pages/inventory/units/index.tsx',
        'js/pages/inventory/opening-balances/create.tsx',
        'js/pages/inventory/adjustments/create.tsx',
    ];

    foreach ($historyAwarePages as $page) {
        expect(file_get_contents(resource_path($page)))
            ->toContain('PreviousPageButton');
    }
});

test('inventory item unit conversion and barcode ui follow the canonical contract', function () {
    $editSource = file_get_contents(
        resource_path('js/pages/inventory/items/edit.tsx'),
    );

    expect($editSource)
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain(
            "import { NativeSelect } from '@/components/ui/native-select';",
        )
        ->not->toContain('<select')
        ->toContain('font-mono text-sm break-all')
        ->toContain('md:flex-row md:items-center md:justify-between')
        ->toContain("'Saving…'")
        ->toContain("'Adding…'")
        ->not->toContain('Saving...');

    $unitEditSource = file_get_contents(
        resource_path('js/pages/inventory/items/unit-edit.tsx'),
    );

    expect($unitEditSource)
        ->toContain("import { Field } from '@/components/ui/field';")
        ->toContain(
            "import { NativeSelect } from '@/components/ui/native-select';",
        )
        ->not->toContain('<select')
        ->not->toContain('border-sidebar-border')
        ->toContain("'Saving…'")
        ->toContain('page.item.name')
        ->toContain("'Edit unit conversion'");
});
