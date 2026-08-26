<?php

use App\Enums\OrganizationRole;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

function usageLimitFixturePlans(array $limits = ['locations' => null, 'seats' => null, 'inventory_items' => null]): void
{
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'prices' => [
                'monthly' => 'price_starter_monthly',
                'yearly' => null,
            ],
            'features' => ['locations.multi'],
            'limits' => $limits,
        ],
    ]);
}

function usageLimitFixtureSubscription(Organization $organization): void
{
    $organization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ]);

    // Subscribing removes the generic trial so plan-declared limits apply.
    $organization->update(['trial_ends_at' => null]);
}

test('a finite member limit rejects creation once reached and leaves no partial record', function () {
    usageLimitFixturePlans(['seats' => 1, 'locations' => null, 'inventory_items' => null]);

    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    usageLimitFixtureSubscription($organization);

    $this->actingAs($owner)
        ->post(
            route('organizations.members.store', $organization),
            ['email' => $applicant->email, 'role' => OrganizationRole::InventoryStaff->value],
        )
        ->assertSessionHasErrors('email');

    $this->assertDatabaseCount('organization_memberships', 1);
    $this->assertDatabaseMissing('organization_memberships', [
        'organization_id' => $organization->id,
        'user_id' => $applicant->id,
    ]);
});

test('a finite member limit allows creation until the ceiling and locks the organization row', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL row-lock SQL is verified in CI.');
    }

    usageLimitFixturePlans(['seats' => 2, 'locations' => null, 'inventory_items' => null]);

    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    usageLimitFixtureSubscription($organization);

    $queries = [];

    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $this->actingAs($owner)
        ->post(
            route('organizations.members.store', $organization),
            ['email' => $applicant->email, 'role' => OrganizationRole::InventoryStaff->value],
        )
        ->assertRedirect(route('organizations.members.index', $organization));

    $this->assertDatabaseCount('organization_memberships', 2);

    expect(
        collect($queries)->contains(
            fn (string $sql): bool => str_contains($sql, 'organizations')
                && str_contains($sql, 'for update'),
        ),
    )->toBeTrue();
});

test('an unlimited member configuration imposes no ceiling', function () {
    usageLimitFixturePlans(['seats' => null, 'locations' => null, 'inventory_items' => null]);

    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    usageLimitFixtureSubscription($organization);

    $this->actingAs($owner)
        ->post(
            route('organizations.members.store', $organization),
            ['email' => $applicant->email, 'role' => OrganizationRole::InventoryStaff->value],
        )
        ->assertRedirect(route('organizations.members.index', $organization));

    $this->assertDatabaseCount('organization_memberships', 2);
});

test('a disabled (undeclared) member limit imposes no ceiling', function () {
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'prices' => ['monthly' => 'price_starter_monthly', 'yearly' => null],
            'features' => ['locations.multi'],
            'limits' => [],
        ],
    ]);

    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    usageLimitFixtureSubscription($organization);

    $this->actingAs($owner)
        ->post(
            route('organizations.members.store', $organization),
            ['email' => $applicant->email, 'role' => OrganizationRole::InventoryStaff->value],
        )
        ->assertRedirect(route('organizations.members.index', $organization));

    $this->assertDatabaseCount('organization_memberships', 2);
});

test('a generic trial organization with no chosen plan is not subject to a quantitative limit', function () {
    usageLimitFixturePlans(['seats' => 1, 'locations' => null, 'inventory_items' => null]);

    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    $this->actingAs($owner)
        ->post(
            route('organizations.members.store', $organization),
            ['email' => $applicant->email, 'role' => OrganizationRole::InventoryStaff->value],
        )
        ->assertRedirect(route('organizations.members.index', $organization));

    $this->assertDatabaseCount('organization_memberships', 2);
});

test('member usage is counted only within the target organization', function () {
    usageLimitFixturePlans(['seats' => 1, 'locations' => null, 'inventory_items' => null]);

    $owner = User::factory()->create();
    $applicant = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    // The other organization already holds several members of its own,
    // which must never count against $organization's seat limit.
    OrganizationMembership::factory()
        ->for($otherOrganization)
        ->count(5)
        ->create();

    usageLimitFixtureSubscription($organization);

    $this->actingAs($owner)
        ->post(
            route('organizations.members.store', $organization),
            ['email' => $applicant->email, 'role' => OrganizationRole::InventoryStaff->value],
        )
        ->assertSessionHasErrors('email');

    $this->assertDatabaseCount('organization_memberships', 6);
});

test('a finite location limit rejects creation once reached and leaves no partial storage area', function () {
    usageLimitFixturePlans(['locations' => 1, 'seats' => null, 'inventory_items' => null]);

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    Location::factory()->for($organization)->create();

    usageLimitFixtureSubscription($organization);

    $this->actingAs($owner)
        ->post(
            route('organizations.locations.store', $organization),
            ['name' => 'Second Kitchen', 'code' => 'SECOND'],
        )
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('storage_locations', 0);
});

test('location usage is counted only within the target organization', function () {
    usageLimitFixturePlans(['locations' => 1, 'seats' => null, 'inventory_items' => null]);

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    Location::factory()->for($otherOrganization)->count(3)->create();

    usageLimitFixtureSubscription($organization);

    $this->actingAs($owner)
        ->post(
            route('organizations.locations.store', $organization),
            ['name' => 'Main Kitchen', 'code' => 'MAIN'],
        )
        ->assertRedirect(route('organizations.locations.index', $organization));

    $this->assertDatabaseCount('locations', 4);
});

test('a finite inventory item limit rejects creation once reached and leaves no partial record', function () {
    usageLimitFixturePlans(['inventory_items' => 1, 'seats' => null, 'locations' => null]);

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    InventoryItem::factory()->for($organization)->create();

    usageLimitFixtureSubscription($organization);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->post(route('inventory.items.store'), [
            'name' => 'Second Item',
            'sku' => 'SKU-SECOND',
            'base_unit_of_measure_id' => UnitOfMeasure::factory()->for($organization)->create()->id,
            'active' => true,
        ])
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('inventory_items', 1);
});

test('inventory item usage is counted only within the target organization', function () {
    usageLimitFixturePlans(['inventory_items' => 1, 'seats' => null, 'locations' => null]);

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    InventoryItem::factory()->for($otherOrganization)->count(3)->create();

    usageLimitFixtureSubscription($organization);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->post(route('inventory.items.store'), [
            'name' => 'First Item',
            'sku' => 'SKU-FIRST',
            'base_unit_of_measure_id' => UnitOfMeasure::factory()->for($organization)->create()->id,
            'active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('inventory_items', 4);
});

test('updating an existing inventory item does not enforce the creation limit', function () {
    usageLimitFixturePlans(['inventory_items' => 1, 'seats' => null, 'locations' => null]);

    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);

    $unit = UnitOfMeasure::factory()->for($organization)->create();
    $item = InventoryItem::factory()->for($organization)->create([
        'base_unit_of_measure_id' => $unit->id,
    ]);

    usageLimitFixtureSubscription($organization);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->put(route('inventory.items.update', $item), [
            'name' => 'Renamed Item',
            'sku' => $item->sku,
            'base_unit_of_measure_id' => $unit->id,
            'active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_items', [
        'id' => $item->id,
        'name' => 'Renamed Item',
    ]);
});
