<?php

use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner sees only locations from the requested organization', function () {
    $owner = User::factory()->create();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    Location::factory()
        ->for($organization)
        ->create([
            'name' => 'Main Restaurant',
            'code' => 'MAIN',
        ]);

    Location::factory()
        ->for($otherOrganization)
        ->create([
            'name' => 'Other Restaurant',
            'code' => 'OTHER',
        ]);

    $this->actingAs($owner)
        ->get(
            route(
                'organizations.locations.index',
                $organization,
            ),
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('organizations/locations/index')
                ->where('organization.id', $organization->id)
                ->has('locations', 1)
                ->where('locations.0.name', 'Main Restaurant')
                ->where('locations.0.code', 'MAIN'),
        );
});

test('an owner can create an organization location', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $this->actingAs($owner)
        ->post(
            route(
                'organizations.locations.store',
                $organization,
            ),
            [
                'name' => '  Main Restaurant  ',
                'code' => ' main ',
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.index',
                $organization,
            ),
        );

    $this->assertDatabaseHas('locations', [
        'organization_id' => $organization->id,
        'name' => 'Main Restaurant',
        'code' => 'MAIN',
        'active' => true,
    ]);
});

test('location codes are unique within an organization', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    Location::factory()
        ->for($organization)
        ->create([
            'code' => 'MAIN',
        ]);

    $this->actingAs($owner)
        ->post(
            route(
                'organizations.locations.store',
                $organization,
            ),
            [
                'name' => 'Duplicate Main',
                'code' => 'main',
            ],
        )
        ->assertSessionHasErrors('code');

    $this->assertDatabaseCount('locations', 1);
});

test('location codes may be reused by different organizations', function () {
    $owner = User::factory()->create();

    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($firstOrganization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    OrganizationMembership::factory()
        ->for($secondOrganization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    Location::factory()
        ->for($firstOrganization)
        ->create([
            'code' => 'MAIN',
        ]);

    $this->actingAs($owner)
        ->post(
            route(
                'organizations.locations.store',
                $secondOrganization,
            ),
            [
                'name' => 'Second Main',
                'code' => 'MAIN',
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.index',
                $secondOrganization,
            ),
        );

    $this->assertDatabaseHas('locations', [
        'organization_id' => $secondOrganization->id,
        'name' => 'Second Main',
        'code' => 'MAIN',
    ]);
});

test('a manager cannot manage locations without locations manage permission', function () {
    $manager = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($manager)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $this->actingAs($manager)
        ->get(
            route(
                'organizations.locations.index',
                $organization,
            ),
        )
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(
            route(
                'organizations.locations.store',
                $organization,
            ),
            [
                'name' => 'Unauthorized Location',
                'code' => 'NOPE',
            ],
        )
        ->assertForbidden();

    $this->assertDatabaseCount('locations', 0);
});

test('an owner can update and deactivate a location without deleting it', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $location = Location::factory()
        ->for($organization)
        ->create([
            'name' => 'Old Name',
            'code' => 'OLD',
            'active' => true,
        ]);

    $this->actingAs($owner)
        ->put(
            route(
                'organizations.locations.update',
                [$organization, $location],
            ),
            [
                'name' => ' Updated Location ',
                'code' => ' updated ',
                'active' => false,
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.index',
                $organization,
            ),
        );

    $this->assertDatabaseHas('locations', [
        'id' => $location->id,
        'organization_id' => $organization->id,
        'name' => 'Updated Location',
        'code' => 'UPDATED',
        'active' => false,
    ]);

    $this->assertDatabaseCount('locations', 1);
});

test('scoped binding rejects a location owned by another organization', function () {
    $owner = User::factory()->create();

    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($firstOrganization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    OrganizationMembership::factory()
        ->for($secondOrganization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $location = Location::factory()
        ->for($secondOrganization)
        ->create([
            'name' => 'Second Organization Location',
            'code' => 'SECOND',
        ]);

    $this->actingAs($owner)
        ->put(
            route(
                'organizations.locations.update',
                [$firstOrganization, $location],
            ),
            [
                'name' => 'Cross Tenant Mutation',
                'code' => 'CROSS',
                'active' => true,
            ],
        )
        ->assertNotFound();

    $this->assertDatabaseHas('locations', [
        'id' => $location->id,
        'organization_id' => $secondOrganization->id,
        'name' => 'Second Organization Location',
        'code' => 'SECOND',
    ]);
});
