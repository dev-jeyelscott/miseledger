<?php

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('an owner has every approved phase zero permission', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    foreach (OrganizationPermission::cases() as $permission) {
        expect(
            Gate::forUser($user)->allows(
                $permission->value,
                $organization,
            ),
        )->toBeTrue();
    }
});

test('an auditor only receives read only permissions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    $allowedPermissions = [
        OrganizationPermission::InventoryView,
        OrganizationPermission::PurchasingView,
        OrganizationPermission::RecipesView,
        OrganizationPermission::ReportsView,
        OrganizationPermission::CostsView,
    ];

    foreach (OrganizationPermission::cases() as $permission) {
        $expected = in_array(
            $permission,
            $allowedPermissions,
            true,
        );

        expect(
            Gate::forUser($user)->allows(
                $permission->value,
                $organization,
            ),
        )->toBe($expected);
    }
});

test('a user without membership has no organization permissions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    foreach (OrganizationPermission::cases() as $permission) {
        expect(
            Gate::forUser($user)->allows(
                $permission->value,
                $organization,
            ),
        )->toBeFalse();
    }
});

test('an auditor cannot manage organization users', function () {
    $auditor = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($auditor)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    $this->actingAs($auditor)
        ->get(
            route(
                'organizations.members.index',
                $organization,
            ),
        )
        ->assertForbidden();
});

test('an auditor cannot adjust inventory', function () {
    $auditor = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($auditor)
        ->create([
            'role' => OrganizationRole::Auditor,
        ]);

    expect(
        Gate::forUser($auditor)->allows(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        ),
    )->toBeFalse();
});

test('cross organization user management is denied', function () {
    $user = User::factory()->create();

    $ownOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($ownOrganization)
        ->for($user)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $this->actingAs($user)
        ->get(
            route(
                'organizations.members.index',
                $otherOrganization,
            ),
        )
        ->assertForbidden();
});
