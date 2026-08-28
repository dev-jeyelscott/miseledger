<?php

use App\Enums\InventoryItemType;
use App\Enums\OrganizationRole;
use App\Models\InventoryBrand;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
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

test('inventory item search keeps partial name matching', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $target = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Fresh Tomato',
            'sku' => 'TOM-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Frozen Corn',
            'sku' => 'CORN-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => 'Fresh Tom',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 1)
                ->where('items.0.id', $target->id)
                ->where('filters.search', 'Fresh Tom'),
        );
});

test('inventory item search keeps partial sku matching independent of item name', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $target = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Fresh Tomato',
            'sku' => 'TOM-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Other Item',
            'sku' => 'OTHER-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => 'TOM-',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 1)
                ->where('items.0.id', $target->id)
                ->where('filters.search', 'TOM-'),
        );
});

test('inventory item search matches a base-item active barcode value', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $target = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Fresh Tomato',
            'sku' => 'TOM-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    InventoryItemBarcode::factory()
        ->for($target)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '0123456789012',
            'active' => true,
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Other Item',
            'sku' => 'OTHER-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
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

test('inventory item search matches an alternate-unit active barcode value', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $target = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Case Of Flour',
            'sku' => 'FLOUR-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    $alternateUnit = InventoryItemUnit::factory()
        ->for($target)
        ->create();

    InventoryItemBarcode::factory()
        ->for($target)
        ->create([
            'organization_id' => $organization->id,
            'inventory_item_unit_id' => $alternateUnit->id,
            'barcode' => '1111111111111',
            'active' => true,
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

test('unknown barcode returns a clean empty inventory result', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Known Item',
            'sku' => 'KNOWN-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '8888888888888',
            'active' => true,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => '9999999999999',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 0)
                ->where('pagination.total', 0)
                ->where('filters.search', '9999999999999'),
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
            'name' => 'Other Organization Item',
            'sku' => 'OTHER-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    InventoryItemBarcode::factory()
        ->for($otherItem)
        ->create([
            'organization_id' => $otherOrganization->id,
            'barcode' => '2222222222222',
            'active' => true,
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
                ->has('items', 0)
                ->where('pagination.total', 0),
        );
});

test('identical barcode values resolve only inside the active organization', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $sharedBarcode = '3333333333333';

    $localItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Local Item',
            'sku' => 'LOCAL-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    InventoryItemBarcode::factory()
        ->for($localItem)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => $sharedBarcode,
            'active' => true,
        ]);

    $otherOrganization = Organization::factory()->create();

    $otherUnit = UnitOfMeasure::factory()
        ->for($otherOrganization)
        ->create();

    $otherItem = InventoryItem::factory()
        ->for($otherOrganization)
        ->create([
            'base_unit_of_measure_id' => $otherUnit->id,
            'name' => 'Other Tenant Item',
            'sku' => 'OTHER-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    InventoryItemBarcode::factory()
        ->for($otherItem)
        ->create([
            'organization_id' => $otherOrganization->id,
            'barcode' => $sharedBarcode,
            'active' => true,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => $sharedBarcode,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 1)
                ->where('items.0.id', $localItem->id)
                ->where('pagination.total', 1),
        );
});

test('category type and status filters compose with barcode search', function () {
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
            'name' => 'Target Ingredient',
            'sku' => 'TARGET-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
            'type' => InventoryItemType::Ingredient,
            'active' => true,
        ]);

    InventoryItemBarcode::factory()
        ->for($target)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '7777000000001',
            'active' => true,
        ]);

    $inactiveItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $produce->id,
            'name' => 'Inactive Ingredient',
            'sku' => 'INACTIVE-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
            'type' => InventoryItemType::Ingredient,
            'active' => false,
        ]);

    InventoryItemBarcode::factory()
        ->for($inactiveItem)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '7777000000002',
            'active' => true,
        ]);

    $wrongCategory = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $pantry->id,
            'name' => 'Pantry Ingredient',
            'sku' => 'PANTRY-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
            'type' => InventoryItemType::Ingredient,
            'active' => true,
        ]);

    InventoryItemBarcode::factory()
        ->for($wrongCategory)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '7777000000003',
            'active' => true,
        ]);

    $wrongType = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $produce->id,
            'name' => 'Produce Packaging',
            'sku' => 'PACK-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
            'type' => InventoryItemType::Packaging,
            'active' => true,
        ]);

    InventoryItemBarcode::factory()
        ->for($wrongType)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '7777000000004',
            'active' => true,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => '7777',
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
                ->where('pagination.total', 1)
                ->where('filters.search', '7777')
                ->where('filters.categoryId', $produce->id)
                ->where(
                    'filters.type',
                    InventoryItemType::Ingredient->value,
                )
                ->where('filters.status', 'active'),
        );
});

test('barcode search pagination totals remain inventory-item based', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $firstItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'First Barcode Item',
            'sku' => 'FIRST-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    InventoryItemBarcode::factory()
        ->for($firstItem)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '4444000000001',
            'active' => true,
        ]);

    InventoryItemBarcode::factory()
        ->for($firstItem)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '4444000000002',
            'active' => true,
        ]);

    $secondItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Second Barcode Item',
            'sku' => 'SECOND-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    InventoryItemBarcode::factory()
        ->for($secondItem)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '4444000000003',
            'active' => true,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => '4444',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 2)
                ->where('pagination.total', 2),
        );
});

test('inactive barcode mappings do not participate in inventory search', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Retired Barcode Item',
            'sku' => 'RET-001',
            'model_number' => null,
            'manufacturer_part_number' => null,
            'active' => true,
        ]);

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '6666666666666',
            'active' => false,
        ]);

    InventoryItemBarcode::factory()
        ->for($item)
        ->create([
            'organization_id' => $organization->id,
            'barcode' => '6677777777777',
            'active' => true,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => '6666666666666',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 0)
                ->where('pagination.total', 0)
                ->where('filters.search', '6666666666666'),
        );
});

test('inventory item filters by brand and never leaks another organization brand', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $acme = InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Acme',
        ]);

    $globex = InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Globex',
        ]);

    $target = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $acme->id,
            'name' => 'Acme Widget',
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => $globex->id,
            'name' => 'Globex Widget',
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_brand_id' => null,
            'name' => 'Unbranded Widget',
        ]);

    $otherOrganization = Organization::factory()->create();

    $otherBrand = InventoryBrand::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Acme',
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'brand' => $acme->id,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 1)
                ->where('items.0.id', $target->id)
                ->where('filters.brandId', $acme->id)
                ->has('brandOptions', 2),
        );

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'brand' => $otherBrand->id,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 0),
        );
});

test('inventory item search matches model number and manufacturer part number', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    $modelMatch = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Convection Oven',
            'model_number' => 'MDL-9000',
            'manufacturer_part_number' => null,
        ]);

    $partMatch = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Mixer Bowl',
            'model_number' => null,
            'manufacturer_part_number' => 'MPN-1234',
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Unrelated Item',
            'model_number' => null,
            'manufacturer_part_number' => null,
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => 'MDL-9000',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->has('items', 1)
                ->where('items.0.id', $modelMatch->id),
        );

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => 'MPN-1234',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->has('items', 1)
                ->where('items.0.id', $partMatch->id),
        );
});

test('inventory item filters return an empty result for a no-match filter combination', function () {
    [$user, $organization, $unit] = inventoryItemsIndexContext();

    InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'name' => 'Fresh Tomato',
            'sku' => 'TOM-001',
        ]);

    $this
        ->withSession([
            'active_organization_id' => $organization->id,
        ])
        ->actingAs($user)
        ->get(route('inventory.items.index', [
            'search' => 'no-such-item-identifier',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('inventory/items/index')
                ->has('items', 0)
                ->where('pagination.total', 0),
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
