<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Recipe;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a manager can create and deactivate a recipe', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('recipes.store'), [
            'code' => '  rcp-001  ',
            'name' => '  Cheeseburger  ',
            'type' => 'menu_item',
            'active' => true,
        ])
        ->assertRedirect(route('recipes.index'));

    $recipe = Recipe::query()->sole();

    expect($recipe->code)->toBe('RCP-001')
        ->and($recipe->name)->toBe('Cheeseburger')
        ->and($recipe->organization_id)->toBe($organization->id)
        ->and($recipe->type->value)->toBe('menu_item')
        ->and($recipe->active)->toBeTrue();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('recipes.update', $recipe), [
            'code' => $recipe->code,
            'name' => $recipe->name,
            'type' => 'menu_item',
            'active' => false,
        ])
        ->assertRedirect(route('recipes.edit', $recipe));

    expect($recipe->refresh()->active)->toBeFalse();
});

test('recipe codes are unique within an organization but reusable elsewhere', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    Recipe::factory()
        ->for($organization)
        ->create([
            'code' => 'RCP-001',
        ]);

    Recipe::factory()
        ->for($otherOrganization)
        ->create([
            'code' => 'RCP-001',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('recipes.store'), [
            'code' => 'RCP-001',
            'name' => 'Duplicate',
            'type' => 'menu_item',
            'active' => true,
        ])
        ->assertSessionHasErrors('code');
});

test('recipes index only exposes the active organization recipes', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $recipe = Recipe::factory()
        ->for($organization)
        ->create([
            'name' => 'Cheeseburger',
        ]);

    Recipe::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Other organization recipe',
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recipes/index')
                ->has('recipes', 1)
                ->where('recipes.0.id', $recipe->id)
                ->where('recipes.0.name', 'Cheeseburger'),
        );
});

test('kitchen staff can view recipes but cannot modify them', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::KitchenStaff,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('recipes.store'), [
            'code' => 'RCP-001',
            'name' => 'Cheeseburger',
            'type' => 'menu_item',
            'active' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('recipes', 0);
});

test('cross organization recipe editing is not exposed', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $recipe = Recipe::factory()
        ->for($otherOrganization)
        ->create();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('recipes.edit', $recipe))
        ->assertNotFound();
});
