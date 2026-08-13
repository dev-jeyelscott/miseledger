<?php

use App\Actions\Organizations\SaveStorageLocation;
use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

test('creating a location also creates its default storage location', function () {
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
                'name' => 'Main Restaurant',
                'code' => 'MAIN',
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.index',
                $organization,
            ),
        );

    $location = Location::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'MAIN')
        ->sole();

    $this->assertDatabaseHas('storage_locations', [
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'name' => StorageLocation::DEFAULT_NAME,
        'code' => StorageLocation::DEFAULT_CODE,
        'active' => true,
    ]);
});

test('an owner can create a location scoped storage location', function () {
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
        ->create();

    $this->actingAs($owner)
        ->post(
            route(
                'organizations.locations.storage-locations.store',
                [$organization, $location],
            ),
            [
                'name' => '  Walk-in Chiller  ',
                'code' => ' chiller ',
                'active' => true,
            ],
        )
        ->assertRedirect(
            route(
                'organizations.locations.storage-locations.index',
                [$organization, $location],
            ),
        );

    $this->assertDatabaseHas('storage_locations', [
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'name' => 'Walk-in Chiller',
        'code' => 'CHILLER',
        'active' => true,
    ]);
});

test('storage codes are unique per location but reusable elsewhere', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $firstLocation = Location::factory()
        ->for($organization)
        ->create();

    $secondLocation = Location::factory()
        ->for($organization)
        ->create();

    $this->actingAs($owner)
        ->post(
            route(
                'organizations.locations.storage-locations.store',
                [$organization, $firstLocation],
            ),
            [
                'name' => 'Dry Storage',
                'code' => 'DRY',
                'active' => true,
            ],
        )
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(
            route(
                'organizations.locations.storage-locations.store',
                [$organization, $firstLocation],
            ),
            [
                'name' => 'Duplicate Dry',
                'code' => 'dry',
                'active' => true,
            ],
        )
        ->assertSessionHasErrors('code');

    $this->actingAs($owner)
        ->post(
            route(
                'organizations.locations.storage-locations.store',
                [$organization, $secondLocation],
            ),
            [
                'name' => 'Second Dry Storage',
                'code' => 'DRY',
                'active' => true,
            ],
        )
        ->assertRedirect();

    $this->assertDatabaseHas('storage_locations', [
        'location_id' => $firstLocation->id,
        'code' => 'DRY',
    ]);

    $this->assertDatabaseHas('storage_locations', [
        'location_id' => $secondLocation->id,
        'code' => 'DRY',
    ]);
});

test('scoped binding rejects a location from another organization', function () {
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

    $secondLocation = Location::factory()
        ->for($secondOrganization)
        ->create();

    $this->actingAs($owner)
        ->post(
            route(
                'organizations.locations.storage-locations.store',
                [$firstOrganization, $secondLocation],
            ),
            [
                'name' => 'Cross Tenant Storage',
                'code' => 'CROSS',
                'active' => true,
            ],
        )
        ->assertNotFound();

    $this->assertDatabaseMissing('storage_locations', [
        'organization_id' => $firstOrganization->id,
        'location_id' => $secondLocation->id,
    ]);
});

test('storage application boundary rejects mismatched tenant ownership', function () {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();

    $secondLocation = Location::factory()
        ->for($secondOrganization)
        ->create();

    expect(
        fn () => app(SaveStorageLocation::class)->handle(
            $firstOrganization,
            $secondLocation,
            [
                'name' => 'Cross Tenant Storage',
                'code' => 'CROSS',
                'active' => true,
            ],
        ),
    )->toThrow(ValidationException::class);

    $this->assertDatabaseCount('storage_locations', 0);
});

test('storage model rejects a location from another organization', function () {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();

    $secondLocation = Location::factory()
        ->for($secondOrganization)
        ->create();

    $storageLocation = new StorageLocation([
        'name' => 'Cross Tenant Storage',
        'code' => 'CROSS',
        'active' => true,
    ]);

    $storageLocation->organization()->associate($firstOrganization);
    $storageLocation->location()->associate($secondLocation);

    expect(
        fn () => $storageLocation->save(),
    )->toThrow(ValidationException::class);

    $this->assertDatabaseCount('storage_locations', 0);
});

test('scoped binding rejects storage belonging to another location', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $firstLocation = Location::factory()
        ->for($organization)
        ->create();

    $secondLocation = Location::factory()
        ->for($organization)
        ->create();

    $storageLocation = app(SaveStorageLocation::class)->handle(
        $organization,
        $secondLocation,
        [
            'name' => 'Second Freezer',
            'code' => 'FREEZER',
            'active' => true,
        ],
    );

    $this->actingAs($owner)
        ->put(
            route(
                'organizations.locations.storage-locations.update',
                [
                    $organization,
                    $firstLocation,
                    $storageLocation,
                ],
            ),
            [
                'name' => 'Cross Location Mutation',
                'code' => 'CROSS',
                'active' => true,
            ],
        )
        ->assertNotFound();

    $this->assertDatabaseHas('storage_locations', [
        'id' => $storageLocation->id,
        'organization_id' => $organization->id,
        'location_id' => $secondLocation->id,
        'name' => 'Second Freezer',
        'code' => 'FREEZER',
    ]);
});

test('an owner can deactivate storage without deleting it', function () {
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
        ->create();

    $storageLocation = app(SaveStorageLocation::class)->handle(
        $organization,
        $location,
        [
            'name' => 'Freezer',
            'code' => 'FREEZER',
            'active' => true,
        ],
    );

    $this->actingAs($owner)
        ->put(
            route(
                'organizations.locations.storage-locations.update',
                [
                    $organization,
                    $location,
                    $storageLocation,
                ],
            ),
            [
                'name' => 'Freezer',
                'code' => 'FREEZER',
                'active' => false,
            ],
        )
        ->assertRedirect();

    $this->assertDatabaseHas('storage_locations', [
        'id' => $storageLocation->id,
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'active' => false,
    ]);

    $this->assertDatabaseCount('storage_locations', 1);

    $this->actingAs($owner)
        ->get(
            route(
                'organizations.locations.storage-locations.index',
                [$organization, $location],
            ),
        )
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component(
                    'organizations/locations/storage-locations/index',
                )
                ->has('storageLocations', 1)
                ->where('storageLocations.0.active', false),
        );
});

test('a manager without locations manage permission cannot manage storage', function () {
    $manager = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($manager)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $location = Location::factory()
        ->for($organization)
        ->create();

    $this->actingAs($manager)
        ->get(
            route(
                'organizations.locations.storage-locations.index',
                [$organization, $location],
            ),
        )
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(
            route(
                'organizations.locations.storage-locations.store',
                [$organization, $location],
            ),
            [
                'name' => 'Unauthorized',
                'code' => 'NOPE',
                'active' => true,
            ],
        )
        ->assertForbidden();
});
