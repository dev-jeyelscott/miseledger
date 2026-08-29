<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can create and deactivate an inventory category', function () {
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
        ->post(route('inventory.categories.store'), [
            'name' => '  Dry   goods  ',
            'active' => true,
        ])
        ->assertRedirect(route('inventory.categories.index'));

    $category = InventoryCategory::query()->sole();

    expect($category->name)->toBe('Dry goods')
        ->and($category->organization_id)->toBe($organization->id)
        ->and($category->active)->toBeTrue();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.categories.update', $category), [
            'name' => 'Dry goods',
            'active' => false,
        ])
        ->assertRedirect(route('inventory.categories.index'));

    expect($category->refresh()->active)->toBeFalse();
});

test('category names are unique within an organization but reusable elsewhere', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Dry goods',
        ]);

    InventoryCategory::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Dry goods',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.categories.store'), [
            'name' => 'Dry goods',
            'active' => true,
        ])
        ->assertSessionHasErrors('name');
});

test('inventory categories index only exposes active organization categories and tenant-safe usage counts', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $category = InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Produce',
        ]);

    InventoryItem::factory()
        ->count(2)
        ->for($organization)
        ->create([
            'inventory_category_id' => $category->id,
        ]);

    $otherCategory = InventoryCategory::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Other organization category',
        ]);

    InventoryItem::factory()
        ->for($otherOrganization)
        ->create([
            'inventory_category_id' => $otherCategory->id,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.categories.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/categories/index')
                ->has('categories', 1)
                ->where('categories.0.id', $category->id)
                ->where('categories.0.name', 'Produce')
                ->where('categories.0.usageCount', 2),
        );
});

test('inventory categories index filters by search and status with normalized props', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $category = InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Dry goods',
            'active' => true,
        ]);

    InventoryCategory::factory()
        ->for($organization)
        ->create([
            'name' => 'Frozen goods',
            'active' => false,
        ]);

    InventoryCategory::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Dry goods foreign',
            'active' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.categories.index', [
            'search' => '  dry  ',
            'status' => 'active',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/categories/index')
                ->has('categories', 1)
                ->where('categories.0.id', $category->id)
                ->where('categories.0.name', 'Dry goods')
                ->where('filters.search', 'dry')
                ->where('filters.status', 'active'),
        );
});

test('inventory categories index rejects an unsupported status filter', function () {
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
        ->get(route('inventory.categories.index', [
            'status' => 'archived',
        ]))
        ->assertSessionHasErrors('status');
});

test('an auditor can view categories but cannot modify them', function () {
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
        ->get(route('inventory.categories.index'))
        ->assertOk();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.categories.store'), [
            'name' => 'Dry goods',
            'active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('inventory_categories', 0);
});

test('standalone inventory category editing is no longer exposed', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $category = InventoryCategory::factory()
        ->for($organization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get("/inventory/categories/{$category->id}/edit")
        ->assertNotFound();
});

test('cross organization inventory category updates are forbidden', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $category = InventoryCategory::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Foreign category',
            'active' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.categories.update', $category), [
            'name' => 'Changed foreign category',
            'active' => false,
        ])
        ->assertForbidden();

    expect($category->refresh()->name)->toBe('Foreign category')
        ->and($category->active)->toBeTrue();
});
