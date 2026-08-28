<?php

use App\Enums\InventoryItemType;
use App\Enums\OrganizationRole;
use App\Models\Barcode;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create the common authenticated inventory-owner test context.
 *
 * @return array{User, Organization, UnitOfMeasure}
 */
function inventoryItemsIndexContext(): array
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
        ]);

    return [$user, $organization, $unit];
}

test('inventory item index is paginated and tenant scoped', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $category = InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Pantry',
        ]);

    InventoryItem::factory()
        ->count(26)
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $category->id,
            'active' => true,
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $category->id,
            'name' => 'Inactive item',
            'sku' => 'INACTIVE-001',
            'active' => false,
        ]);

    $otherOrganization = Organization::factory()->create();

    $otherUnit = UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create();

    InventoryCategory::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Other tenant category',
        ]);

    InventoryItem::factory()
        ->for($otherOrganization)
        ->create([
            'base_unit_of_measure_id' => $otherUnit->id,
            'name' => 'Other tenant item',
            'sku' => 'OTHER-001',
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'status' => 'active',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 25)
                ->where('pagination.total', 26)
                ->where('pagination.current_page', 1)
                ->where('pagination.per_page', 25)
                ->where(
                    'pagination.next_page_url',
                    fn (mixed $url): bool => is_string($url)
                        && str_contains($url, 'status=active'),
                )
                ->where('summary.total', 27)
                ->where('summary.active', 26)
                ->where('filters.status', 'active')
                ->has('categoryOptions', 1)
                ->where('categoryOptions.0.id', $category->id)
                ->where('canManage', true),
        );
});

test('inventory item filters combine search category type and status', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $produce = InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Produce',
        ]);

    $pantry = InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Pantry',
        ]);

    $target = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $produce->id,
            'name' => 'Fresh Tomato',
            'sku' => 'TOM-001',
            'type' => InventoryItemType::Ingredient,
            'active' => true,
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $produce->id,
            'name' => 'Tomato Trim',
            'sku' => 'TOM-002',
            'type' => InventoryItemType::Ingredient,
            'active' => false,
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $pantry->id,
            'name' => 'Tomato Packaging',
            'sku' => 'TOM-003',
            'type' => InventoryItemType::Packaging,
            'active' => true,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => 'tom',
            'category' => $produce->id,
            'type' => InventoryItemType::Ingredient->value,
            'status' => 'active',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 1)
                ->where('items.0.id', $target->id)
                ->where('items.0.name', 'Fresh Tomato')
                ->where('filters.search', 'tom')
                ->where('filters.categoryId', $produce->id)
                ->where(
                    'filters.type',
                    InventoryItemType::Ingredient->value,
                )
                ->where('filters.status', 'active'),
        );
});

test('inventory item search matches a base-item barcode value', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $target = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Fresh Tomato',
            'sku' => 'TOM-001',
        ]);

    Barcode::factory()
        ->for($target)
        ->create([
            'organization_id' => $organization->id,
            'value' => '0123456789012',
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Other Item',
            'sku' => 'OTHER-001',
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => '0123456789012',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 1)
                ->where('items.0.id', $target->id)
                ->where('filters.search', '0123456789012'),
        );
});

test('inventory item search matches an alternate-unit barcode value', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $target = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Case Of Flour',
            'sku' => 'FLOUR-001',
        ]);

    $alternateUnit = InventoryItemUnit::factory()
        ->for($target)
        ->create();

    Barcode::factory()
        ->for($target)
        ->create([
            'organization_id' => $organization->id,
            'inventory_item_unit_id' => $alternateUnit->id,
            'value' => '1111111111111',
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => '1111111111111',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 1)
                ->where('items.0.id', $target->id),
        );
});

test('inventory item search does not resolve a barcode from another organization', function () {
    [$user, $organization] = inventoryItemsIndexContext();

    $otherOrganization = Organization::factory()->create();
    $otherUnit = UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create();

    $otherItem = InventoryItem::factory()
        ->for($otherOrganization)
        ->create([
            'base_unit_of_measure_id' => $otherUnit->id,
        ]);

    Barcode::factory()
        ->for($otherItem)
        ->create([
            'organization_id' => $otherOrganization->id,
            'value' => '2222222222222',
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => '2222222222222',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 0),
        );
});

test('inventory item index supports deterministic sorting', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Alpha Item',
            'sku' => 'A-001',
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Zulu Item',
            'sku' => 'Z-001',
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'sort' => 'sku',
            'direction' => 'desc',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 2)
                ->where('items.0.sku', 'Z-001')
                ->where('items.1.sku', 'A-001')
                ->where('filters.sort', 'sku')
                ->where('filters.direction', 'desc'),
        );
});

test('auditor inventory index remains read only', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    $unit = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
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
                ->has('items', 1)
                ->where('canManage', false),
        );
});
