<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Config;

function featureEntitlementFixturePlans(): void
{
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'prices' => [
                'monthly' => 'price_starter_monthly',
                'yearly' => null,
            ],
            'features' => ['inventory.view'],
            'limits' => ['locations' => 1, 'seats' => null],
        ],
        'growth' => [
            'name' => 'Growth',
            'prices' => [
                'monthly' => 'price_growth_monthly',
                'yearly' => null,
            ],
            'features' => ['inventory.view', 'purchasing', 'recipes', 'reports.export', 'locations.multi'],
            'limits' => ['locations' => 5, 'seats' => null],
        ],
    ]);
}

function featureEntitlementSubscription(Organization $organization, array $attributes = []): void
{
    $organization->subscriptions()->create(array_merge([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ], $attributes));
}

test('a plan lacking the purchasing feature is rejected on direct route access to suppliers, purchase orders, and receiving', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('suppliers.index'))
        ->assertForbidden();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('purchase-orders.index'))
        ->assertForbidden();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('goods-receipts.index'))
        ->assertForbidden();
});

test('a plan granting the purchasing feature allows direct route access to suppliers', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization, ['stripe_price' => 'price_growth_monthly']);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('suppliers.index'))
        ->assertOk();
});

test('a plan lacking the recipes feature is rejected on direct route access to recipes', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('recipes.index'))
        ->assertForbidden();
});

test('a plan lacking the exports feature is rejected on a direct report export request', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.stock-on-hand.export'))
        ->assertForbidden();
});

test('a plan granting the exports feature allows a direct report export request', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization, ['stripe_price' => 'price_growth_monthly']);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.stock-on-hand.export'))
        ->assertOk();
});

test('a plan lacking the multi location feature is rejected on direct route access to organization locations', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('organizations.locations.index', $organization))
        ->assertForbidden();
});

test('an unknown plan price fails closed and denies every paid only feature', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization, ['stripe_price' => 'price_unrecognized']);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('recipes.index'))
        ->assertForbidden();
});

test('a generic trial organization with no chosen plan is granted every gated feature', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk();

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('inventory.stock-on-hand.export'))
        ->assertOk();
});

test('existing role permissions continue to protect a feature granted route regardless of plan feature access', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::KitchenStaff]);

    featureEntitlementSubscription($organization, ['stripe_price' => 'price_growth_monthly']);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('suppliers.index'))
        ->assertForbidden();
});

test('the shared entitlement context resolves the same feature grants used to enforce route access', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization);

    $deniedResponse = $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect($deniedResponse->viewData('page')['props']['organizationContext']['entitlements']['grants'])
        ->toBe([
            'purchasing' => false,
            'recipes' => false,
            'reports.export' => false,
            'locations.multi' => false,
        ]);

    $organization->subscription(config('billing.subscription_type'))->update([
        'stripe_price' => 'price_growth_monthly',
    ]);

    $grantedResponse = $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect($grantedResponse->viewData('page')['props']['organizationContext']['entitlements']['grants'])
        ->toBe([
            'purchasing' => true,
            'recipes' => true,
            'reports.export' => true,
            'locations.multi' => true,
        ]);
});

test('report pages expose the reports.export grant used to conditionally render the export control', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization);

    $reportRoutes = [
        'inventory.stock-movements.index',
        'inventory.valuation.index',
        'inventory.purchasing-history.index',
        'waste.index',
    ];

    foreach ($reportRoutes as $reportRoute) {
        $deniedResponse = $this->withSession(['active_organization_id' => $organization->id])
            ->actingAs($user)
            ->get(route($reportRoute))
            ->assertOk();

        expect($deniedResponse->viewData('page')['props']['organizationContext']['entitlements']['grants']['reports.export'])
            ->toBeFalse();
    }

    $organization->subscription(config('billing.subscription_type'))->update([
        'stripe_price' => 'price_growth_monthly',
    ]);

    foreach ($reportRoutes as $reportRoute) {
        $grantedResponse = $this->withSession(['active_organization_id' => $organization->id])
            ->actingAs($user)
            ->get(route($reportRoute))
            ->assertOk();

        expect($grantedResponse->viewData('page')['props']['organizationContext']['entitlements']['grants']['reports.export'])
            ->toBeTrue();
    }
});

test('report pages only render the export control when the reports.export grant is true', function () {
    $reportPageFiles = [
        'js/pages/inventory/stock-movement-ledger.tsx',
        'js/pages/inventory/valuation.tsx',
        'js/pages/inventory/purchasing-history.tsx',
        'js/pages/waste/index.tsx',
    ];

    foreach ($reportPageFiles as $reportPageFile) {
        $source = (string) file_get_contents(resource_path($reportPageFile));

        expect($source)
            ->toContain("grants['reports.export'] ?? false")
            ->toContain('{canExportReports && (');
    }
});

test('a plan lacking the recipes feature is rejected on a direct recipe creation mutation', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('recipes.store'), [])
        ->assertForbidden();
});

test('a plan granting the recipes feature does not block a direct recipe creation mutation with the feature gate', function () {
    featureEntitlementFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    featureEntitlementSubscription($organization, ['stripe_price' => 'price_growth_monthly']);

    $response = $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->post(route('recipes.store'), []);

    expect($response->status())->not->toBe(403);
});
