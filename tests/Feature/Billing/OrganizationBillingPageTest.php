<?php

use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Config;

function organizationBillingPageFixturePlans(): void
{
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'prices' => [
                'monthly' => 'price_starter_monthly',
                'yearly' => null,
            ],
            'features' => ['recipes'],
            'limits' => ['locations' => 3],
        ],
        'growth' => [
            'name' => 'Growth',
            'prices' => [
                'monthly' => 'price_growth_monthly',
                'yearly' => 'price_growth_yearly',
            ],
            'features' => [],
            'limits' => [],
        ],
    ]);
}

function createBillingPageSubscription(Organization $organization, BillingProvider $provider = BillingProvider::Stripe): BillingSubscription
{
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => $provider]);

    return BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => $provider,
        'type' => config('billing.subscription_type'),
        'external_plan_id' => 'price_starter_monthly',
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'provider_status' => 'active',
    ]);
}

test('an owner can view the billing page for a subscribed organization', function () {
    organizationBillingPageFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    createBillingPageSubscription($organization);

    $response = $this->actingAs($user)->get(
        route('organizations.billing.show', $organization),
    );

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('organizations/billing/index')
            ->where('organization.id', $organization->id)
            ->where('subscription.plan', 'starter')
            ->where('subscription.status', 'active')
            ->where('subscription.accessMode', 'writable')
            ->where('entitlements.features', ['recipes'])
            ->where('entitlements.limits', ['locations' => 3]),
    );
});

test('manual QR Ph plans are available from configured minor-unit amounts without recurring PayMongo plan IDs', function () {
    $billingConfig = config('billing');
    Config::set('billing.provider', 'paymongo');
    Config::set('billing.providers.paymongo.manual_qrph', true);
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'manual_amounts' => ['monthly' => 49_900, 'yearly' => null],
            'providers' => [
                'stripe' => ['monthly' => null, 'yearly' => null],
                'paymongo' => ['monthly' => null, 'yearly' => null],
            ],
            'features' => [],
            'limits' => [],
        ],
    ]);

    try {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => OrganizationRole::Owner]);

        $this->actingAs($user)->get(route('organizations.billing.show', $organization))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('manualQrPhEnabled', true)
                ->where('availablePlans.0.monthly', true)
                ->where('availablePlans.0.yearly', false));
    } finally {
        Config::set('billing', $billingConfig);
    }
});

test('a commercially read-only organization can still view the billing page', function () {
    organizationBillingPageFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'stripe_id' => 'cus_test_123',
        'trial_ends_at' => now()->subDay(),
    ]);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $response = $this->actingAs($user)->get(
        route('organizations.billing.show', $organization),
    );

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('organizations/billing/index')
            ->where('subscription.accessMode', 'read_only')
            ->where('subscription.plan', null)
            ->has('availablePlans', 2),
    );
});

test('the billing page never exposes Stripe secrets or raw provider objects', function () {
    organizationBillingPageFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    createBillingPageSubscription($organization);

    $response = $this->actingAs($user)->get(
        route('organizations.billing.show', $organization),
    );

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $props = $page->toArray()['props'];

        expect($props)->not->toHaveKey('stripe');
        expect($props['subscription'])->not->toHaveKey('stripeId');
        expect(json_encode($props))->not->toContain('sk_test_')
            ->not->toContain('cus_test_123')
            ->not->toContain('price_starter_monthly');
    });
});

test('a PayMongo-owned subscription receives server-driven management data without external identifiers', function () {
    organizationBillingPageFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($user)->create(['role' => OrganizationRole::Owner]);
    createBillingPageSubscription($organization, BillingProvider::PayMongo)->update([
        'current_period_ends_at' => now()->addMonth(),
        'next_billing_at' => now()->addMonth(),
    ]);

    $response = $this->actingAs($user)->get(route('organizations.billing.show', $organization));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('subscription.planName', 'Starter')
        ->where('subscription.status', 'active')
        ->where('subscription.interval', 'monthly')
        ->where('subscription.management', 'cancel')
        ->has('subscription.nextBillingAt'));

    expect($response->getContent())->not->toContain('subs_')
        ->not->toContain('cus_');
});

test('a non-owner without billing.manage is denied the billing page', function () {
    organizationBillingPageFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Manager]);

    $this->actingAs($user)->get(
        route('organizations.billing.show', $organization),
    )->assertForbidden();
});

test('a user with no membership in the target organization is denied the billing page', function () {
    organizationBillingPageFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    $this->actingAs($user)->get(
        route('organizations.billing.show', $organization),
    )->assertForbidden();
});

test('an owner of a different organization cannot view another organization billing page', function () {
    organizationBillingPageFixturePlans();

    $user = User::factory()->create();
    $ownedOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($ownedOrganization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    createBillingPageSubscription($otherOrganization);

    $this->actingAs($user)->get(
        route('organizations.billing.show', $otherOrganization),
    )->assertForbidden();
});
