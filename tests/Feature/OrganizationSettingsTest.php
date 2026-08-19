<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can view and update organization settings', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create([
        'name' => 'Original Name',
        'slug' => 'original-name',
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
        'active' => true,
    ]);

    OrganizationMembership::factory()->for($organization)->for($owner)->create([
        'role' => OrganizationRole::Owner,
    ]);

    $this->actingAs($owner)
        ->get(route('organizations.settings.edit', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/settings')
            ->where('organization.id', $organization->id)
            ->where('organization.name', 'Original Name')
            ->where('organization.slug', 'original-name')
            ->where('organization.timezone', 'Asia/Manila')
            ->where('organization.currency', 'PHP')
            ->where('organization.active', true));

    $this->actingAs($owner)
        ->put(route('organizations.settings.update', $organization), [
            'name' => ' Updated Organization ',
            'slug' => ' UPDATED_ORGANIZATION ',
            'timezone' => ' America/New_York ',
            'currency' => ' usd ',
            'active' => true,
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'name' => 'Updated Organization',
        'slug' => 'updated_organization',
        'timezone' => 'America/New_York',
        'currency' => 'USD',
        'active' => true,
    ]);
});

test('organization settings reject invalid timezones and currencies', function (
    string $field,
    string $value,
) {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->for($organization)->for($owner)->create([
        'role' => OrganizationRole::Owner,
    ]);

    $settings = [
        'name' => $organization->name,
        'slug' => $organization->slug,
        'timezone' => $organization->timezone,
        'currency' => $organization->currency,
        'active' => true,
    ];
    $settings[$field] = $value;

    $this->actingAs($owner)
        ->put(route('organizations.settings.update', $organization), $settings)
        ->assertSessionHasErrors($field);

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'timezone' => 'Asia/Manila',
        'currency' => 'PHP',
    ]);
})->with([
    'invalid IANA timezone' => ['timezone', 'Not/A-Timezone'],
    'invalid ISO currency' => ['currency', 'ZZZ'],
]);

test('organization settings deny users outside the organization boundary', function () {
    $owner = User::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->for($organization)->for($owner)->create([
        'role' => OrganizationRole::Owner,
    ]);

    $this->actingAs($owner)
        ->get(route('organizations.settings.edit', $otherOrganization))
        ->assertForbidden();

    $this->actingAs($owner)
        ->put(route('organizations.settings.update', $otherOrganization), [
            'name' => 'Cross Tenant Update',
            'slug' => 'cross-tenant-update',
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
            'active' => false,
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('organizations', [
        'id' => $otherOrganization->id,
        'active' => true,
    ]);
});

test('non-owner members cannot manage organization settings', function () {
    $manager = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->for($organization)->for($manager)->create([
        'role' => OrganizationRole::Manager,
    ]);

    $this->actingAs($manager)
        ->get(route('organizations.settings.edit', $organization))
        ->assertForbidden();

    $this->actingAs($manager)
        ->put(route('organizations.settings.update', $organization), [
            'name' => 'Unauthorized Update',
            'slug' => 'unauthorized-update',
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
            'active' => false,
        ])
        ->assertForbidden();
});
