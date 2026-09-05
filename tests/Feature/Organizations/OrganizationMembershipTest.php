<?php

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a guest cannot create an organization', function () {
    $this->post(route('organizations.store'), [
        'name' => 'Unauthorized Restaurant',
    ])->assertRedirect(route('login'));

    $this->assertDatabaseCount('organizations', 0);
    $this->assertDatabaseCount('organization_memberships', 0);
});

test('an unverified user cannot create an organization', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), [
            'name' => 'Unverified Restaurant',
        ])
        ->assertRedirect(route('verification.notice'));

    $this->assertDatabaseCount('organizations', 0);
    $this->assertDatabaseCount('organization_memberships', 0);
});

test('organization creation validates its name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), [
            'name' => '   ',
        ])
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('organizations', 0);
});

test('a verified user creates an organization and owner membership', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('organizations.store'), [
            'name' => 'Mise Restaurant Group',
        ]);

    $organization = Organization::query()->sole();
    $membership = OrganizationMembership::query()->sole();

    $response
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(
            'active_organization_id',
            $organization->id,
        );

    expect($organization->name)
        ->toBe('Mise Restaurant Group')
        ->and($organization->timezone)
        ->toBe('Asia/Manila')
        ->and($organization->currency)
        ->toBe('PHP')
        ->and($organization->active)
        ->toBeTrue()
        ->and($membership->user_id)
        ->toBe($user->id)
        ->and($membership->organization_id)
        ->toBe($organization->id)
        ->and($membership->role)
        ->toBe(OrganizationRole::Owner);
});

test('the dashboard resolves the selected active organization', function () {
    $user = User::factory()->create();

    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($firstOrganization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    OrganizationMembership::factory()
        ->for($secondOrganization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    $this->withSession([
        'active_organization_id' => $secondOrganization->id,
    ])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('dashboard')
                ->where(
                    'organizationContext.active.id',
                    $secondOrganization->id,
                )
                ->where(
                    'organizationContext.active.name',
                    $secondOrganization->name,
                ),
        );
});

test('a user can activate an organization they belong to', function () {
    $user = User::factory()->create();

    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($firstOrganization)
        ->for($user)
        ->create();

    OrganizationMembership::factory()
        ->for($secondOrganization)
        ->for($user)
        ->create();

    $this->withSession([
        'active_organization_id' => $firstOrganization->id,
    ])
        ->actingAs($user)
        ->put(
            route(
                'organizations.activate',
                $secondOrganization,
            ),
        )
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(
            'active_organization_id',
            $secondOrganization->id,
        );
});

test('a user cannot activate another organizations tenant context', function () {
    $user = User::factory()->create();

    $ownOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($ownOrganization)
        ->for($user)
        ->create();

    $this->withSession([
        'active_organization_id' => $ownOrganization->id,
    ])
        ->actingAs($user)
        ->put(
            route(
                'organizations.activate',
                $otherOrganization,
            ),
        )
        ->assertForbidden()
        ->assertSessionHas(
            'active_organization_id',
            $ownOrganization->id,
        );
});

test('an invalid active tenant session falls back to a valid membership', function () {
    $user = User::factory()->create();

    $ownOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($ownOrganization)
        ->for($user)
        ->create();

    $this->withSession([
        'active_organization_id' => $otherOrganization->id,
    ])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSessionHas(
            'active_organization_id',
            $ownOrganization->id,
        )
        ->assertInertia(
            fn (Assert $page) => $page->where(
                'organizationContext.active.id',
                $ownOrganization->id,
            ),
        );
});

test('a users organizations retain independent administrative active state', function () {
    $user = User::factory()->create();

    $suspendedOrganization = Organization::factory()->create([
        'active' => false,
    ]);
    $activeOrganization = Organization::factory()->create([
        'active' => true,
    ]);

    OrganizationMembership::factory()
        ->for($suspendedOrganization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    OrganizationMembership::factory()
        ->for($activeOrganization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    expect($suspendedOrganization->fresh()->active)
        ->toBeFalse()
        ->and($activeOrganization->fresh()->active)
        ->toBeTrue()
        ->and(
            $user->hasOrganizationPermission(
                $suspendedOrganization,
                OrganizationPermission::OrganizationManage,
            ),
        )
        ->toBeFalse()
        ->and(
            $user->hasOrganizationPermission(
                $activeOrganization,
                OrganizationPermission::OrganizationManage,
            ),
        )
        ->toBeTrue();

    $this->withSession([
        'active_organization_id' => $suspendedOrganization->id,
    ])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSessionHas(
            'active_organization_id',
            $activeOrganization->id,
        )
        ->assertInertia(
            fn (Assert $page) => $page->where(
                'organizationContext.active.id',
                $activeOrganization->id,
            ),
        );
});

test('an owner can add an existing registered organization member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
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
                'organizations.members.store',
                $organization,
            ),
            [
                'email' => $member->email,
                'role' => OrganizationRole::InventoryStaff->value,
            ],
        )
        ->assertRedirect(
            route(
                'organizations.members.index',
                $organization,
            ),
        );

    $this->assertDatabaseHas('organization_memberships', [
        'organization_id' => $organization->id,
        'user_id' => $member->id,
        'role' => OrganizationRole::InventoryStaff->value,
    ]);
});

test('a manager cannot add an organization member', function () {
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($manager)
        ->create([
            'role' => OrganizationRole::Manager,
        ]);

    $this->actingAs($manager)
        ->post(
            route(
                'organizations.members.store',
                $organization,
            ),
            [
                'email' => $member->email,
                'role' => OrganizationRole::InventoryStaff->value,
            ],
        )
        ->assertForbidden();

    $this->assertDatabaseMissing('organization_memberships', [
        'organization_id' => $organization->id,
        'user_id' => $member->id,
    ]);
});

test('duplicate organization membership is rejected', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($member)
        ->create([
            'role' => OrganizationRole::KitchenStaff,
        ]);

    $this->actingAs($owner)
        ->post(
            route(
                'organizations.members.store',
                $organization,
            ),
            [
                'email' => $member->email,
                'role' => OrganizationRole::Manager->value,
            ],
        )
        ->assertSessionHasErrors('email');

    $this->assertDatabaseCount('organization_memberships', 2);
});

test('an owner can enable a member AI access without changing their organization role', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create(['role' => OrganizationRole::Owner]);
    $membership = OrganizationMembership::factory()
        ->for($organization)
        ->for($member)
        ->create([
            'role' => OrganizationRole::KitchenStaff,
            'ai_enabled' => false,
        ]);

    $this->actingAs($owner)
        ->put(route('organizations.members.ai-access.update', [
            'organization' => $organization,
            'membership' => $membership,
        ]), ['ai_enabled' => true])
        ->assertRedirect(route('organizations.members.index', $organization));

    $this->assertDatabaseHas('organization_memberships', [
        'id' => $membership->id,
        'role' => OrganizationRole::KitchenStaff->value,
        'ai_enabled' => true,
    ]);
});

test('a non-owner cannot change a member AI access', function () {
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($manager)
        ->create(['role' => OrganizationRole::Manager]);
    $membership = OrganizationMembership::factory()
        ->for($organization)
        ->for($member)
        ->create(['ai_enabled' => false]);

    $this->actingAs($manager)
        ->put(route('organizations.members.ai-access.update', [
            'organization' => $organization,
            'membership' => $membership,
        ]), ['ai_enabled' => true])
        ->assertForbidden();

    $this->assertDatabaseHas('organization_memberships', [
        'id' => $membership->id,
        'ai_enabled' => false,
    ]);
});

test('the active organization exposes only the current members AI capability', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create([
        'trial_ends_at' => now()->addDay(),
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
            'ai_enabled' => false,
        ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.ai.canUse', true)
                ->where('organizationContext.ai.memberEnabled', true),
        );
});
