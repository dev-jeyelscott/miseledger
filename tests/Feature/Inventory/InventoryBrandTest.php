<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryBrand;
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
        ->assertRedirect(route('inventory.brands.edit', $brand));

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

    $this->assertDatabaseCount('inventory_brands', 0);
});

test('cross organization inventory brand editing is not exposed', function () {
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
        ->get(route('inventory.brands.edit', $brand))
        ->assertNotFound();
});
