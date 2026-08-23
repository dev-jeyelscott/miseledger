<?php

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('each MVP role receives only its assigned organization permissions', function (
    OrganizationRole $role,
    array $allowedPermissions,
) {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => $role,
        ]);

    foreach (OrganizationPermission::cases() as $permission) {
        expect(
            Gate::forUser($user)->allows(
                $permission->value,
                $organization,
            ),
        )->toBe(in_array($permission, $allowedPermissions, true));
    }
})->with([
    'owner' => [OrganizationRole::Owner, OrganizationPermission::cases()],
    'manager' => [
        OrganizationRole::Manager,
        [
            OrganizationPermission::InventoryView,
            OrganizationPermission::InventoryAdjust,
            OrganizationPermission::PurchasingView,
            OrganizationPermission::PurchasingManage,
            OrganizationPermission::ReceivingFinalize,
            OrganizationPermission::CountsCreate,
            OrganizationPermission::CountsFinalize,
            OrganizationPermission::WasteRecord,
            OrganizationPermission::TransfersCreate,
            OrganizationPermission::TransfersShip,
            OrganizationPermission::TransfersReceive,
            OrganizationPermission::RecipesView,
            OrganizationPermission::RecipesManage,
            OrganizationPermission::ReportsView,
            OrganizationPermission::CostsView,
        ],
    ],
    'inventory staff' => [
        OrganizationRole::InventoryStaff,
        [
            OrganizationPermission::InventoryView,
            OrganizationPermission::InventoryAdjust,
            OrganizationPermission::PurchasingView,
            OrganizationPermission::ReceivingFinalize,
            OrganizationPermission::CountsCreate,
            OrganizationPermission::CountsFinalize,
            OrganizationPermission::WasteRecord,
            OrganizationPermission::TransfersCreate,
            OrganizationPermission::TransfersShip,
            OrganizationPermission::TransfersReceive,
            OrganizationPermission::ReportsView,
        ],
    ],
    'kitchen staff' => [
        OrganizationRole::KitchenStaff,
        [
            OrganizationPermission::InventoryView,
            OrganizationPermission::WasteRecord,
            OrganizationPermission::RecipesView,
        ],
    ],
    'auditor' => [
        OrganizationRole::Auditor,
        [
            OrganizationPermission::InventoryView,
            OrganizationPermission::PurchasingView,
            OrganizationPermission::RecipesView,
            OrganizationPermission::ReportsView,
            OrganizationPermission::CostsView,
        ],
    ],
]);

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

test('organization active remains an administrative toggle independent of any commercial state', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create([
        'active' => true,
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    $this->actingAs($owner)
        ->put(
            route('organizations.settings.update', $organization),
            [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'active' => false,
            ],
        )
        ->assertRedirect(route('dashboard'));

    $organization->refresh();

    expect($organization->active)->toBeFalse();

    expect(
        Gate::forUser($owner)->allows('view', $organization),
    )->toBeFalse();

    expect(
        $owner->hasOrganizationPermission(
            $organization,
            OrganizationPermission::OrganizationManage,
        ),
    )->toBeFalse();

    $organization->update(['active' => true]);
    $organization->refresh();

    expect($organization->active)->toBeTrue();

    expect(
        Gate::forUser($owner)->allows('view', $organization),
    )->toBeTrue();
});

test('only owner receives billing manage across every MVP role', function (
    OrganizationRole $role,
    bool $expected,
) {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'role' => $role,
        ]);

    expect(
        Gate::forUser($user)->allows(
            OrganizationPermission::BillingManage->value,
            $organization,
        ),
    )->toBe($expected);
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'manager' => [OrganizationRole::Manager, false],
    'inventory staff' => [OrganizationRole::InventoryStaff, false],
    'kitchen staff' => [OrganizationRole::KitchenStaff, false],
    'auditor' => [OrganizationRole::Auditor, false],
]);

test('cross organization billing authorization is denied', function () {
    $owner = User::factory()->create();

    $ownOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($ownOrganization)
        ->for($owner)
        ->create([
            'role' => OrganizationRole::Owner,
        ]);

    expect(
        Gate::forUser($owner)->allows(
            OrganizationPermission::BillingManage->value,
            $otherOrganization,
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
