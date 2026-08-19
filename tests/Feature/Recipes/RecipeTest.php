<?php

use App\Enums\OrganizationRole;
use App\Enums\RecipeType;
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

test('recipe modal mutations return to the exact invoking index context', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $query = [
        'search' => 'Burger',
        'type' => RecipeType::MenuItem->value,
        'activity' => 'active',
        'sort' => 'updated_at',
        'direction' => 'desc',
        'per_page' => 25,
        'page' => 2,
    ];

    $relativeReturnTo = route('recipes.index', $query, false);
    $expectedReturnTo = route('recipes.index', $query);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->post(route('recipes.store'), [
            'code' => 'RCP-100',
            'name' => 'Burger',
            'type' => RecipeType::MenuItem->value,
            'active' => true,
            'return_to' => $relativeReturnTo,
        ])
        ->assertRedirect($expectedReturnTo);

    $recipe = Recipe::query()->sole();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('recipes.update', $recipe), [
            'code' => 'RCP-100',
            'name' => 'Updated Burger',
            'type' => RecipeType::MenuItem->value,
            'active' => true,
            'return_to' => $relativeReturnTo,
        ])
        ->assertRedirect($expectedReturnTo);

    expect($recipe->refresh()->name)->toBe('Updated Burger');
});

test('unsafe recipe return targets use canonical fallbacks', function () {
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
            'code' => 'RCP-101',
            'name' => 'Safe Redirect Recipe',
            'type' => RecipeType::MenuItem->value,
            'active' => true,
            'return_to' => 'https://example.invalid/phishing',
        ])
        ->assertRedirect(route('recipes.index'));

    $recipe = Recipe::query()->sole();

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->put(route('recipes.update', $recipe), [
            'code' => $recipe->code,
            'name' => 'Updated Safe Redirect Recipe',
            'type' => RecipeType::MenuItem->value,
            'active' => true,
            'return_to' => '//example.invalid/phishing',
        ])
        ->assertRedirect(route('recipes.edit', $recipe));
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
                ->has('rows', 1)
                ->where('rows.0.id', $recipe->id)
                ->where('rows.0.name', 'Cheeseburger')
                ->where('rows.0.versionCount', 0)
                ->where('rows.0.publishedVersionCount', 0)
                ->where('rows.0.draftVersionCount', 0)
                ->where('rows.0.latestVersionNumber', null)
                ->where('pagination.total', 1)
                ->where('summary.totalCount', 1)
                ->missing('rows.0.cost'),
        );
});

test('recipes index filters recipe identity data while keeping tenant summary stable', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $matchingRecipe = Recipe::factory()
        ->for($organization)
        ->create([
            'code' => 'RCP-300',
            'name' => 'Alpha Burger',
            'type' => RecipeType::MenuItem,
            'active' => true,
        ]);

    Recipe::factory()
        ->for($organization)
        ->create([
            'code' => 'RCP-200',
            'name' => 'Burger Sauce',
            'type' => RecipeType::PreparedItem,
            'active' => true,
        ]);

    Recipe::factory()
        ->for($organization)
        ->create([
            'code' => 'RCP-100',
            'name' => 'Legacy Burger',
            'type' => RecipeType::MenuItem,
            'active' => false,
        ]);

    Recipe::factory()
        ->for($otherOrganization)
        ->create([
            'code' => 'RCP-999',
            'name' => 'Hidden Burger',
            'type' => RecipeType::MenuItem,
            'active' => true,
        ]);

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('recipes.index', [
            'search' => 'Burger',
            'type' => RecipeType::MenuItem->value,
            'activity' => 'active',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 10,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recipes/index')
                ->has('rows', 1)
                ->where('rows.0.id', $matchingRecipe->id)
                ->where('filters.search', 'Burger')
                ->where('filters.type', RecipeType::MenuItem->value)
                ->where('filters.activity', 'active')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('filters.perPage', 10)
                ->where('summary.totalCount', 3)
                ->where('summary.activeCount', 2)
                ->where('summary.menuItemCount', 2)
                ->where('summary.preparedItemCount', 1)
                ->where('summary.batchCount', 0)
                ->where('canManage', true)
                ->where('canViewCosts', true),
        );
});

test('recipes index sorts and paginates deterministically', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    foreach (range(1, 12) as $index) {
        Recipe::factory()
            ->for($organization)
            ->create([
                'code' => sprintf('RCP-%03d', $index),
                'name' => sprintf('Recipe %02d', $index),
            ]);
    }

    $query = [
        'sort' => 'name',
        'direction' => 'asc',
        'per_page' => 10,
    ];

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('recipes.index', $query))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('rows', 10)
                ->where('rows.0.name', 'Recipe 01')
                ->where('rows.9.name', 'Recipe 10')
                ->where('pagination.currentPage', 1)
                ->where('pagination.lastPage', 2)
                ->where('pagination.total', 12),
        );

    $this->withSession([
        'active_organization_id' => $organization->id,
    ])
        ->actingAs($user)
        ->get(route('recipes.index', [
            ...$query,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('rows', 2)
                ->where('rows.0.name', 'Recipe 11')
                ->where('rows.1.name', 'Recipe 12')
                ->where('pagination.currentPage', 2)
                ->where('pagination.total', 12),
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
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('recipes/index')
                ->where('canManage', false)
                ->where('canViewCosts', false),
        );

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
