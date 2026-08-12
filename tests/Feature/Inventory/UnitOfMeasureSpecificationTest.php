<?php

use App\Actions\Organizations\CreateOrganization;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Inventory\StandardUnits;

test('organization creation seeds the deterministic standard unit set', function () {
    $user = User::factory()->create();

    $organization = app(CreateOrganization::class)->handle(
        $user,
        'Standard Unit Restaurant',
    );

    expect(
        $organization->unitsOfMeasure()->count(),
    )->toBe(count(StandardUnits::definitions()));

    foreach (StandardUnits::definitions() as $definition) {
        $this->assertDatabaseHas('units_of_measure', [
            'organization_id' => $organization->id,
            'name' => $definition['name'],
            'symbol' => $definition['symbol'],
            'dimension' => $definition['dimension'],
            'active' => true,
        ]);
    }
});

test('an owner can create a dimensioned custom unit', function () {
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
            route('inventory.units.store'),
            [
                'name' => 'Quart',
                'symbol' => 'qt',
                'dimension' => 'volume',
                'active' => true,
            ],
        )
        ->assertRedirect(
            route('inventory.units.index'),
        );

    $this->assertDatabaseHas('units_of_measure', [
        'organization_id' => $organization->id,
        'name' => 'Quart',
        'symbol' => 'qt',
        'dimension' => 'volume',
        'active' => true,
    ]);
});

test('reserved standard symbol rejects an incompatible dimension', function () {
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
            route('inventory.units.store'),
            [
                'name' => 'Invalid Kilogram',
                'symbol' => 'kg',
                'dimension' => 'volume',
                'active' => true,
            ],
        )
        ->assertSessionHasErrors('dimension');

    $this->assertDatabaseMissing('units_of_measure', [
        'organization_id' => $organization->id,
        'symbol' => 'kg',
    ]);
});

test('legacy style standard unit input derives its approved dimension', function () {
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
            route('inventory.units.store'),
            [
                'name' => 'Kilogram',
                'symbol' => 'kg',
                'active' => true,
            ],
        )
        ->assertRedirect();

    $this->assertDatabaseHas('units_of_measure', [
        'organization_id' => $organization->id,
        'symbol' => 'kg',
        'dimension' => 'weight',
    ]);
});

test('invalid dimensions are rejected', function () {
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
            route('inventory.units.store'),
            [
                'name' => 'Meter',
                'symbol' => 'm',
                'dimension' => 'length',
                'active' => true,
            ],
        )
        ->assertSessionHasErrors('dimension');

    $this->assertDatabaseMissing('units_of_measure', [
        'organization_id' => $organization->id,
        'symbol' => 'm',
    ]);
});
