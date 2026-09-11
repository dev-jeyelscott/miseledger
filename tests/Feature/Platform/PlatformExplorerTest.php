<?php

use App\Enums\OrganizationAccessMode;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationRolloutClassification;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('platform explorer routes preserve the existing platform administrator boundary', function () {
    $normalUser = User::factory()->create();
    $platformUser = User::factory()->create();
    $targetUser = User::factory()->create();
    $organization = Organization::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($normalUser)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs($normalUser)
        ->get(route('admin.organizations.index'))
        ->assertForbidden();

    $this->actingAs($platformUser)
        ->get(route('admin.users.index'))
        ->assertOk();

    $this->actingAs($platformUser)
        ->get(route('admin.users.show', $targetUser))
        ->assertOk();

    $this->actingAs($platformUser)
        ->get(route('admin.organizations.index'))
        ->assertOk();

    $this->actingAs($platformUser)
        ->get(route('admin.organizations.show', $organization))
        ->assertOk();
});

test('platform explorer exposes only read-only HTTP routes', function () {
    foreach ([
        'admin.users.index',
        'admin.users.show',
        'admin.organizations.index',
        'admin.organizations.show',
    ] as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route?->methods())->toBe(['GET', 'HEAD']);
    }

    expect(Route::has('admin.users.store'))->toBeFalse()
        ->and(Route::has('admin.users.update'))->toBeFalse()
        ->and(Route::has('admin.users.destroy'))->toBeFalse()
        ->and(Route::has('admin.organizations.store'))->toBeFalse()
        ->and(Route::has('admin.organizations.update'))->toBeFalse()
        ->and(Route::has('admin.organizations.destroy'))->toBeFalse();
});

test('users index is bounded searchable filterable sortable and prop minimized', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $target = User::factory()->create([
        'name' => 'POC Search Target',
        'email' => 'poc-search@example.com',
        'email_verified_at' => now(),
    ]);

    $organization = Organization::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $target->getKey(),
        'role' => OrganizationRole::Auditor,
    ]);

    User::factory()->count(30)->create();

    $this->actingAs($platformUser)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('users', 25)
                ->where('pagination.per_page', 25)
                ->where('pagination.total', 32),
        );

    $this->actingAs($platformUser)
        ->get(route('admin.users.index', [
            'search' => 'POC Search',
            'verification' => 'verified',
            'role' => OrganizationRole::Auditor->value,
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 15,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('users', 1)
                ->where('users.0.id', $target->id)
                ->where('users.0.name', $target->name)
                ->where('users.0.email', $target->email)
                ->where('users.0.membershipCount', 1)
                ->missing('users.0.password')
                ->missing('users.0.two_factor_secret')
                ->missing('users.0.two_factor_recovery_codes')
                ->missing('users.0.two_factor_confirmed_at')
                ->missing('users.0.remember_token')
                ->missing('users.0.passkeys')
                ->missing('users.0.sessions')
                ->where('filters.search', 'POC Search')
                ->where('filters.verification', 'verified')
                ->where(
                    'filters.role',
                    OrganizationRole::Auditor->value,
                )
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('filters.perPage', 15),
        );
});

test('user detail exposes bounded safe memberships and organization cross-link identifiers', function () {
    $platformUser = User::factory()->create();
    $target = User::factory()->withTwoFactor()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $organization = Organization::factory()->create([
        'name' => 'Cross Link Organization',
        'active' => false,
    ]);

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $target->getKey(),
        'role' => OrganizationRole::Manager,
    ]);

    $this->actingAs($platformUser)
        ->get(route('admin.users.show', $target))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/users/show')
                ->where('user.id', $target->id)
                ->where('user.name', $target->name)
                ->where('user.email', $target->email)
                ->where('user.membershipCount', 1)
                ->missing('user.password')
                ->missing('user.two_factor_secret')
                ->missing('user.two_factor_recovery_codes')
                ->missing('user.two_factor_confirmed_at')
                ->missing('user.remember_token')
                ->has('memberships', 1)
                ->where(
                    'memberships.0.organization.id',
                    $organization->id,
                )
                ->where(
                    'memberships.0.organization.name',
                    'Cross Link Organization',
                )
                ->where(
                    'memberships.0.organization.active',
                    false,
                )
                ->where(
                    'memberships.0.role',
                    OrganizationRole::Manager->value,
                ),
        );
});

test('organization index keeps administrative active separate from resolver commercial access', function () {
    Carbon::setTestNow('2026-09-11 12:00:00');

    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $activeReadOnly = Organization::factory()->create([
        'name' => 'Active But Read Only',
        'active' => true,
        'rollout_classification' => OrganizationRolloutClassification::ImmediatelyBillable,
        'trial_ends_at' => now()->subDay(),
    ]);

    $inactiveWritable = Organization::factory()->create([
        'name' => 'Inactive But Commercially Writable',
        'active' => false,
        'rollout_classification' => OrganizationRolloutClassification::TrialEligible,
        'trial_ends_at' => now()->addDay(),
    ]);

    $activeExpected = OrganizationSubscriptionAccessResolver::resolve(
        $activeReadOnly,
    );
    $inactiveExpected = OrganizationSubscriptionAccessResolver::resolve(
        $inactiveWritable,
    );

    expect($activeExpected->accessMode)
        ->toBe(OrganizationAccessMode::ReadOnly)
        ->and($inactiveExpected->accessMode)
        ->toBe(OrganizationAccessMode::Writable);

    $this->actingAs($platformUser)
        ->get(route('admin.organizations.index', [
            'search' => 'Active But Read Only',
            'status' => 'active',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('organizations', 1)
                ->where(
                    'organizations.0.id',
                    $activeReadOnly->id,
                )
                ->where('organizations.0.active', true)
                ->where(
                    'organizations.0.commercialAccess.accessMode',
                    $activeExpected->accessMode->value,
                )
                ->missing(
                    'organizations.0.commercialAccess.external_subscription_id',
                )
                ->missing(
                    'organizations.0.commercialAccess.external_customer_id',
                )
                ->missing(
                    'organizations.0.commercialAccess.stripe_id',
                ),
        );

    $this->actingAs($platformUser)
        ->get(route('admin.organizations.index', [
            'search' => 'Inactive But Commercially Writable',
            'status' => 'inactive',
        ]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('organizations', 1)
                ->where(
                    'organizations.0.id',
                    $inactiveWritable->id,
                )
                ->where('organizations.0.active', false)
                ->where(
                    'organizations.0.commercialAccess.accessMode',
                    $inactiveExpected->accessMode->value,
                ),
        );

    Carbon::setTestNow();
});

test('organization detail exposes bounded safe member and location context', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    $organization = Organization::factory()->create([
        'rollout_classification' => OrganizationRolloutClassification::TrialEligible,
        'trial_ends_at' => now()->addWeek(),
    ]);

    $member = User::factory()->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $organization->getKey(),
        'user_id' => $member->getKey(),
        'role' => OrganizationRole::Owner,
    ]);

    $location = Location::factory()->create([
        'organization_id' => $organization->getKey(),
        'name' => 'Main Kitchen',
        'code' => 'MAIN',
        'active' => true,
    ]);

    $expectedAccess = OrganizationSubscriptionAccessResolver::resolve(
        $organization,
    );

    $this->actingAs($platformUser)
        ->get(route('admin.organizations.show', $organization))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/organizations/show')
                ->where('organization.id', $organization->id)
                ->where('organization.memberCount', 1)
                ->where('organization.locationCount', 1)
                ->where(
                    'organization.commercialAccess.accessMode',
                    $expectedAccess->accessMode->value,
                )
                ->missing('organization.stripe_id')
                ->missing('organization.pm_type')
                ->missing('organization.pm_last_four')
                ->missing('organization.billingCustomers')
                ->missing('organization.billingSubscriptions')
                ->has('members', 1)
                ->where('members.0.user.id', $member->id)
                ->where('members.0.user.email', $member->email)
                ->missing('members.0.user.password')
                ->missing('members.0.user.two_factor_secret')
                ->missing('members.0.user.two_factor_recovery_codes')
                ->has('locations', 1)
                ->where('locations.0.id', $location->id)
                ->where('locations.0.name', 'Main Kitchen')
                ->where('locations.0.code', 'MAIN'),
        );
});

test('organization explorer resolves commercial access with bounded billing queries', function () {
    $platformUser = User::factory()->create();

    PlatformAdmin::query()->create([
        'user_id' => $platformUser->getKey(),
    ]);

    Organization::factory()->count(12)->create([
        'rollout_classification' => OrganizationRolloutClassification::ImmediatelyBillable,
        'trial_ends_at' => now()->subDay(),
    ]);

    $queries = [];

    DB::listen(
        function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        },
    );

    $this->actingAs($platformUser)
        ->get(route('admin.organizations.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('organizations', 12),
        );

    $billingQueries = collect($queries)
        ->filter(
            static fn (string $sql): bool => str_contains($sql, 'billing_subscriptions')
                || str_contains($sql, 'billing_customers')
                || (
                    str_contains($sql, 'subscriptions')
                    && ! str_contains($sql, 'billing_subscriptions')
                ),
        )
        ->values();

    expect($billingQueries->count())
        ->toBeLessThanOrEqual(3);
});

test('platform pages use Wayfinder cross-links without hardcoded admin URLs', function () {
    $layout = (string) file_get_contents(
        resource_path('js/layouts/platform-layout.tsx'),
    );
    $userIndex = (string) file_get_contents(
        resource_path('js/pages/admin/users/index.tsx'),
    );
    $userShow = (string) file_get_contents(
        resource_path('js/pages/admin/users/show.tsx'),
    );
    $organizationIndex = (string) file_get_contents(
        resource_path('js/pages/admin/organizations/index.tsx'),
    );
    $organizationShow = (string) file_get_contents(
        resource_path('js/pages/admin/organizations/show.tsx'),
    );

    expect($layout)
        ->toContain('PlatformUserController.index()')
        ->toContain('PlatformOrganizationController.index()')
        ->and($userIndex)
        ->toContain('PlatformUserController.show(')
        ->and($userShow)
        ->toContain('PlatformOrganizationController.show(')
        ->and($organizationIndex)
        ->toContain('PlatformOrganizationController.show(')
        ->and($organizationShow)
        ->toContain('PlatformUserController.show(')
        ->and($layout)
        ->not->toContain('href="/admin/users"')
        ->not->toContain('href="/admin/organizations"')
        ->and($userIndex)
        ->not->toContain('href="/admin/')
        ->and($userShow)
        ->not->toContain('href="/admin/')
        ->and($organizationIndex)
        ->not->toContain('href="/admin/')
        ->and($organizationShow)
        ->not->toContain('href="/admin/');
});
