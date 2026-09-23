<?php

use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createMobileMember(
    Organization $organization,
    OrganizationRole $role = OrganizationRole::Manager,
): User {
    $user = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    return $user;
}

test('resolves a remembered valid location', function (): void {
    $organization = Organization::factory()->create();
    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);
    $user = createMobileMember($organization);

    $this->actingAs($user)
        ->withSession([
            'active_organization_id' => $organization->id,
            'mobile_location_by_org' => [$organization->id => $location->id],
        ])
        ->get('/mobile')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('mobile/home')
                ->where('activeLocation.id', $location->id),
        );
});

test('falls back to first accessible location and flashes a notice when the remembered location is invalid', function (): void {
    $organization = Organization::factory()->create();
    $staleLocation = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => false,
    ]);
    $fallbackLocation = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
        'name' => 'AAA Fallback',
    ]);
    $user = createMobileMember($organization);

    $this->actingAs($user)
        ->withSession([
            'active_organization_id' => $organization->id,
            'mobile_location_by_org' => [$organization->id => $staleLocation->id],
        ])
        ->get('/mobile')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('mobile/home')
                ->where('activeLocation.id', $fallbackLocation->id),
        );

    expect(session('mobile_location_by_org')[$organization->id])
        ->toBe($fallbackLocation->id);
});

test('leaves location null when the organization has zero active locations', function (): void {
    $organization = Organization::factory()->create();
    $user = createMobileMember($organization);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $organization->id])
        ->get('/mobile')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('mobile/home')
                ->where('activeLocation', null)
                ->where('hasLocations', false),
        );
});

test('redirects to the picker when nothing is remembered yet', function (): void {
    $organization = Organization::factory()->create();
    Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);
    $user = createMobileMember($organization);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $organization->id])
        ->get('/mobile')
        ->assertRedirect('/mobile/location?next=%2Fmobile');
});

test('post mobile location rejects a location from a different organization', function (): void {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $foreignLocation = Location::factory()->create([
        'organization_id' => $otherOrganization->id,
        'active' => true,
    ]);
    $user = createMobileMember($organization);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $organization->id])
        ->post('/mobile/location', ['location_id' => $foreignLocation->id])
        ->assertSessionHasErrors('location_id');

    expect(session('mobile_location_by_org.'.$organization->id))->toBeNull();
});

test('post mobile location persists a valid selection and redirects to next', function (): void {
    $organization = Organization::factory()->create();
    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);
    $user = createMobileMember($organization);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $organization->id])
        ->post('/mobile/location', [
            'location_id' => $location->id,
            'next' => '/mobile',
        ])
        ->assertRedirect('/mobile');

    expect(session('mobile_location_by_org.'.$organization->id))
        ->toBe($location->id);
});

test('post mobile location rejects an external next url', function (): void {
    $organization = Organization::factory()->create();
    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);
    $user = createMobileMember($organization);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $organization->id])
        ->post('/mobile/location', [
            'location_id' => $location->id,
            'next' => 'https://evil.example/steal',
        ])
        ->assertSessionHasErrors('next');
});
