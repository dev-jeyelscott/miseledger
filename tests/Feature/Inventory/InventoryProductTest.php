<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\InventoryProduct;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can create, update, and view a product family', function () {
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
        ->post(route('inventory.product-families.store'), [
            'name' => '  Stand   Mixers  ',
            'active' => true,
        ])
        ->assertRedirect();

    $product = InventoryProduct::query()->sole();

    expect($product->name)->toBe('Stand Mixers')
        ->and($product->organization_id)->toBe($organization->id)
        ->and($product->active)->toBeTrue();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.product-families.update', $product), [
            'name' => 'Stand Mixers',
            'active' => false,
        ])
        ->assertRedirect();

    expect($product->refresh()->active)->toBeFalse();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.product-families.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/product-families/show')
            ->where('productFamily.id', $product->id)
            ->where('productFamily.name', 'Stand Mixers')
            ->where('productFamily.active', false));
});

test('product family index filters by search and status with normalized tenant scoped props', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $product = InventoryProduct::factory()
        ->for($organization)
        ->create([
            'name' => 'Cordless drills',
            'active' => true,
        ]);

    InventoryProduct::factory()
        ->for($organization)
        ->create([
            'name' => 'Corded drills',
            'active' => false,
        ]);

    InventoryProduct::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Cordless drills foreign',
            'active' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.product-families.index', [
            'search' => '  cordless  ',
            'status' => 'active',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/product-families/index')
                ->has('productFamilies', 1)
                ->where('productFamilies.0.id', $product->id)
                ->where('productFamilies.0.name', 'Cordless drills')
                ->where('productFamilies.0.active', true)
                ->where('filters.search', 'cordless')
                ->where('filters.status', 'active'),
        );
});

test('product family index rejects an unsupported status filter', function () {
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
        ->get(route('inventory.product-families.index', [
            'status' => 'archived',
        ]))
        ->assertSessionHasErrors('status');
});

test('product family names are unique within an organization but reusable elsewhere', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    InventoryProduct::factory()
        ->for($organization)
        ->create(['name' => 'Stand Mixers']);

    InventoryProduct::factory()
        ->for($otherOrganization)
        ->create(['name' => 'Stand Mixers']);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.product-families.store'), [
            'name' => 'Stand Mixers',
            'active' => true,
        ])
        ->assertSessionHasErrors('name');
});

test('cross organization product family retrieval is not exposed', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $product = InventoryProduct::factory()
        ->for($otherOrganization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.product-families.show', $product))
        ->assertNotFound();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.product-families.update', $product), [
            'name' => 'Hijacked',
            'active' => true,
        ])
        ->assertForbidden();
});

test('an inventory item can optionally belong to a product family within its organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $itemWithoutFamily = InventoryItem::factory()
        ->for($organization)
        ->create();

    expect($itemWithoutFamily->fresh()->inventory_product_id)->toBeNull();

    $product = InventoryProduct::factory()
        ->for($organization)
        ->create();

    $itemWithFamily = InventoryItem::factory()
        ->for($organization)
        ->create(['inventory_product_id' => $product->id]);

    expect($itemWithFamily->inventoryProduct->id)->toBe($product->id)
        ->and($itemWithFamily->fresh()->sku)->not->toBeNull();
});

test('tenant crossing product family assignment on an inventory item is rejected', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $foreignProduct = InventoryProduct::factory()
        ->for($otherOrganization)
        ->create();

    $unit = UnitOfMeasure::factory()
        ->for($organization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.items.store'), [
            'name' => 'Cross Tenant Item',
            'sku' => 'SKU-CROSS-1',
            'base_unit_of_measure_id' => $unit->id,
            'inventory_product_id' => $foreignProduct->id,
            'type' => 'ingredient',
            'yield_percentage' => '100.00',
            'active' => true,
        ])
        ->assertSessionHasErrors('inventory_product_id');

    $this->assertDatabaseMissing('inventory_items', [
        'sku' => 'SKU-CROSS-1',
    ]);
});

test('a product family does not change stock movement or stock balance behavior', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $product = InventoryProduct::factory()
        ->for($organization)
        ->create();

    $item = InventoryItem::factory()
        ->for($organization)
        ->create(['inventory_product_id' => $product->id]);

    expect($item->stockMovements()->count())->toBe(0);

    $this->assertDatabaseHas('inventory_items', [
        'id' => $item->id,
        'sku' => $item->sku,
        'base_unit_of_measure_id' => $item->base_unit_of_measure_id,
        'inventory_product_id' => $product->id,
    ]);
});
