<?php

use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('unauthenticated visitors are redirected to login with the correct intended url', function (): void {
    $this->get('/mobile')
        ->assertRedirect('/login');

    $this->get('/mobile');

    expect(session('url.intended'))->toBe(url('/mobile'));
});

test('authenticated user with no active organization sees an empty state without a 500', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/mobile')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('mobile/home')
                ->where('organization', null),
        );
});

test('authenticated org member with a remembered location reaches home', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationRole::Manager,
    ]);

    $location = Location::factory()->create([
        'organization_id' => $organization->id,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->withSession([
            'active_organization_id' => $organization->id,
            'mobile_location_by_org' => [$organization->id => $location->id],
        ])
        ->get('/mobile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('mobile/home'));
});
