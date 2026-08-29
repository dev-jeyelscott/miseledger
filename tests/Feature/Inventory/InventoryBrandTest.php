<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryBrand;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can create and deactivate an inventory brand', function () {
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
        ->post(route('inventory.brands.store'), [
            'name' => '  Acme   Foods  ',
            'active' => true,
        ])
        ->assertRedirect(route('inventory.brands.index'));

    $brand = InventoryBrand::query()->sole();

    expect($brand->name)->toBe('Acme Foods')
        ->and($brand->organization_id)->toBe($organization->id)
        ->and($brand->active)->toBeTrue();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.brands.update', $brand), [
            'name' => 'Acme Foods',
            'active' => false,
        ])
        ->assertRedirect(route('inventory.brands.index'));

    expect($brand->refresh()->active)->toBeFalse();
});

test('brand names are unique within an organization but reusable elsewhere', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Acme Foods',
        ]);

    InventoryBrand::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Acme Foods',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.brands.store'), [
            'name' => 'Acme Foods',
            'active' => true,
        ])
        ->assertSessionHasErrors('name');
});

test('inventory brands index only exposes the active organization brands', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $brand = InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Local Brand',
        ]);

    InventoryBrand::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Other organization brand',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.brands.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/brands/index')
                ->has('brands', 1)
                ->where('brands.0.id', $brand->id)
                ->where('brands.0.name', 'Local Brand'),
        );
});

test('an auditor can view brands but cannot modify them', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    $brand = InventoryBrand::factory()
        ->for($organization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.brands.index'))
        ->assertOk();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('inventory.brands.store'), [
            'name' => 'Acme Foods',
            'active' => true,
        ])
        ->assertForbidden();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.brands.update', $brand), [
            'name' => 'Updated Brand',
            'active' => false,
        ])
        ->assertForbidden();

    expect($brand->refresh()->name)->not->toBe('Updated Brand');
});

test('inventory brands index filters by search and status with normalized props', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Acme Foods',
            'active' => true,
        ]);

    InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Beta Supplies',
            'active' => false,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.brands.index', [
            'search' => '  acme  ',
            'status' => 'active',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/brands/index')
                ->has('brands', 1)
                ->where('brands.0.name', 'Acme Foods')
                ->where('filters.search', 'acme')
                ->where('filters.status', 'active'),
        );
});

test('inventory brands index rejects an unsupported status filter', function () {
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
        ->get(route('inventory.brands.index', [
            'status' => 'archived',
        ]))
        ->assertSessionHasErrors('status');
});

test('inventory brands index orders by active then name and reports tenant scoped usage counts', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $zebra = InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Zebra',
            'active' => true,
        ]);

    $apple = InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Apple',
            'active' => true,
        ]);

    $inactive = InventoryBrand::factory()
        ->for($organization)
        ->create([
            'name' => 'Inactive Brand',
            'active' => false,
        ]);

    InventoryItem::factory()
        ->for($organization)
        ->count(2)
        ->create([
            'inventory_brand_id' => $apple->id,
        ]);

    $otherBrand = InventoryBrand::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Apple',
        ]);

    InventoryItem::factory()
        ->for($otherOrganization)
        ->create([
            'inventory_brand_id' => $otherBrand->id,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('inventory.brands.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('inventory/brands/index')
                ->has('brands', 3)
                ->where('brands.0.id', $apple->id)
                ->where('brands.0.usageCount', 2)
                ->where('brands.1.id', $zebra->id)
                ->where('brands.1.usageCount', 0)
                ->where('brands.2.id', $inactive->id),
        );
});

test('cross organization inventory brand updates remain unavailable', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $brand = InventoryBrand::factory()
        ->for($otherOrganization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('inventory.brands.update', $brand), [
            'name' => 'Updated Brand',
            'active' => false,
        ])
        ->assertForbidden();

    expect($brand->refresh()->name)->not->toBe('Updated Brand');
});

test('the former inventory brand edit URL returns not found', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $brand = InventoryBrand::factory()
        ->for($organization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get('/inventory/brands/'.$brand->id.'/edit')
        ->assertNotFound();
});
