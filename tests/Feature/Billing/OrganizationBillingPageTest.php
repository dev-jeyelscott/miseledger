<?php

use App\Enums\OrganizationRole;
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

test('an owner can view the billing page for a subscribed organization', function () {
    organizationBillingPageFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['stripe_id' => 'cus_test_123']);

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create(['role' => OrganizationRole::Owner]);

    $organization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ]);

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

    $organization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ]);

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

    $otherOrganization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ]);

    $this->actingAs($user)->get(
        route('organizations.billing.show', $otherOrganization),
    )->assertForbidden();
});
