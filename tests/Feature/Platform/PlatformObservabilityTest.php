<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformAdmin;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the observability landing page to login', function () {
    $this->get(route('admin.observability.index'))
        ->assertRedirect(route('login'));
});

test('normal authenticated users cannot access the observability landing page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.observability.index'))
        ->assertForbidden();
});

test('organization owners cannot access the observability landing page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $user->getKey(),
        'role' => OrganizationRole::Owner,
    ]);

    $this->actingAs($user)
        ->get(route('admin.observability.index'))
        ->assertForbidden();
});

test('platform admins with zero organization memberships can access the observability landing page and see dashboard links', function () {
    $user = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    expect($user->organizationMemberships()->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('admin.observability.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/observability')
                ->where('pulse.url', route('pulse'))
                ->where('horizon.url', route('horizon.index'))
                ->has('pulse.enabled'),
        );
});

test('platform admins without an approved strong factor are routed to security settings from the observability landing page', function () {
    $user = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.observability.index'))
        ->assertRedirect(route('security.edit'));
});

test('guests are redirected from the native Horizon dashboard to login', function () {
    $this->get(route('horizon.index'))
        ->assertRedirect(route('login'));
});

test('normal authenticated users cannot access the native Horizon dashboard by direct URL', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('horizon.index'))
        ->assertForbidden();
});

test('organization owners cannot access the native Horizon dashboard by direct URL', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $user->getKey(),
        'role' => OrganizationRole::Owner,
    ]);

    $this->actingAs($user)
        ->get(route('horizon.index'))
        ->assertForbidden();
});

test('platform admins with zero organization memberships can access the native Horizon dashboard', function () {
    $user = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('horizon.index'))
        ->assertOk();
});

test('guests are redirected from the native Pulse dashboard to login', function () {
    $this->get(route('pulse'))
        ->assertRedirect(route('login'));
});

test('normal authenticated users cannot access the native Pulse dashboard by direct URL', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pulse'))
        ->assertForbidden();
});

test('organization owners cannot access the native Pulse dashboard by direct URL', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $user->getKey(),
        'role' => OrganizationRole::Owner,
    ]);

    $this->actingAs($user)
        ->get(route('pulse'))
        ->assertForbidden();
});

test('platform admins with zero organization memberships can access the native Pulse dashboard', function () {
    $user = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->get(route('pulse'))
        ->assertOk();
});

test('the platform navigation links to the observability landing page', function () {
    $layout = (string) file_get_contents(
        resource_path('js/layouts/platform-layout.tsx'),
    );

    expect($layout)
        ->toContain('Observability')
        ->toContain('PlatformObservabilityController');
});
