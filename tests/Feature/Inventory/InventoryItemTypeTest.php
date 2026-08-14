<?php

use App\Enums\InventoryItemType;
use App\Enums\OrganizationRole;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can create each approved inventory item type', function (
    InventoryItemType $type,
) {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();
    $category = InventoryCategory::factory()->for($organization)->create();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Item '.$type->value,
            'sku' => 'SKU-'.strtoupper($type->value),
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $category->id,
            'type' => $type->value,
            'yield_percentage' => '87.50',
            'active' => true,
        ])
        ->assertRedirect();

    $item = InventoryItem::query()->sole();

    expect($item->type)->toBe($type)
        ->and($item->inventory_category_id)->toBe($category->id)
        ->and($item->yield_percentage)->toBe('87.50');
})->with([
    'ingredient' => InventoryItemType::Ingredient,
    'finished item' => InventoryItemType::FinishedItem,
    'prepared item' => InventoryItemType::PreparedItem,
    'packaging' => InventoryItemType::Packaging,
    'consumable' => InventoryItemType::Consumable,
]);

test('an inventory item defaults to a one hundred percent yield', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
            'base_unit_of_measure_id' => $unit->id,
            'active' => true,
        ])
        ->assertRedirect();

    expect(InventoryItem::query()->sole()->yield_percentage)
        ->toBe('100.00');
});

test('an inventory item rejects a category owned by another organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();
    $otherCategory = InventoryCategory::factory()
        ->for($otherOrganization)
        ->create();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Flour',
            'sku' => 'FLOUR-001',
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $otherCategory->id,
            'active' => true,
        ])
        ->assertSessionHasErrors('inventory_category_id');

    $this->assertDatabaseCount('inventory_items', 0);
});

test('an inventory item is deactivated without being deleted', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();
    $item = InventoryItem::factory()
        ->for($organization)
        ->create(['base_unit_of_measure_id' => $unit->id]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->put(route('inventory.items.update', $item), [
            'name' => $item->name,
            'sku' => $item->sku,
            'base_unit_of_measure_id' => $unit->id,
            'active' => false,
        ])
        ->assertRedirect(route('inventory.items.edit', $item));

    expect($item->refresh()->active)->toBeFalse();
    $this->assertModelExists($item);
});

test('an assigned inactive category can be retained but not newly assigned', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();
    $category = InventoryCategory::factory()->for($organization)->create();
    $assignedItem = InventoryItem::factory()
        ->for($organization)
        ->create([
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $category->id,
        ]);
    $otherItem = InventoryItem::factory()
        ->for($organization)
        ->create(['base_unit_of_measure_id' => $unit->id]);

    $category->update(['active' => false]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.items.edit', $assignedItem))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/items/edit')
                ->has('categories', 1)
                ->where('categories.0.id', $category->id)
                ->where('categories.0.active', false),
        );

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->put(route('inventory.items.update', $assignedItem), [
            'name' => 'Updated item name',
            'sku' => $assignedItem->sku,
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $category->id,
            'active' => true,
        ])
        ->assertRedirect(route('inventory.items.edit', $assignedItem));

    expect($assignedItem->refresh()->inventory_category_id)
        ->toBe($category->id)
        ->and($assignedItem->name)->toBe('Updated item name');

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->put(route('inventory.items.update', $otherItem), [
            'name' => $otherItem->name,
            'sku' => $otherItem->sku,
            'base_unit_of_measure_id' => $unit->id,
            'inventory_category_id' => $category->id,
            'active' => true,
        ])
        ->assertSessionHasErrors('inventory_category_id');

    expect($otherItem->refresh()->inventory_category_id)->toBeNull();
});
