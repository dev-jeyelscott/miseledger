<?php

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

function subscriptionContextFixturePlans(): void
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
            'features' => ['inventory.view', 'reports.view'],
            'limits' => ['locations' => 5, 'seats' => null],
        ],
    ]);
}

function createSubscriptionContextOrganizationSubscription(Organization $organization, array $attributes = []): void
{
    $organization->subscriptions()->create(array_merge([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
    ], $attributes));
}

test('an authenticated members active organization exposes its subscription and entitlement context', function () {
    subscriptionContextFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create();

    createSubscriptionContextOrganizationSubscription($organization, [
        'stripe_status' => 'past_due',
    ]);

    $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.subscription.plan', 'starter')
                ->where('organizationContext.subscription.status', 'past_due')
                ->where('organizationContext.subscription.accessMode', 'writable')
                ->where('organizationContext.subscription.onTrial', false)
                ->where('organizationContext.subscription.billingWarning', true)
                ->where('organizationContext.entitlements.features', ['inventory.view'])
                ->where('organizationContext.entitlements.limits', ['locations' => 1, 'seats' => null]),
        );
});

test('switching the active organization changes the exposed billing and entitlement context', function () {
    subscriptionContextFixturePlans();

    $user = User::factory()->create();

    $starterOrganization = Organization::factory()->create();
    $growthOrganization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($starterOrganization)
        ->for($user)
        ->create();

    OrganizationMembership::factory()
        ->for($growthOrganization)
        ->for($user)
        ->create();

    createSubscriptionContextOrganizationSubscription($starterOrganization, [
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
    ]);

    createSubscriptionContextOrganizationSubscription($growthOrganization, [
        'stripe_status' => 'active',
        'stripe_price' => 'price_growth_monthly',
    ]);

    $this->withSession(['active_organization_id' => $starterOrganization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.subscription.plan', 'starter')
                ->where('organizationContext.entitlements.features', ['inventory.view']),
        );

    $this->withSession(['active_organization_id' => $growthOrganization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.subscription.plan', 'growth')
                ->where('organizationContext.entitlements.features', ['inventory.view', 'reports.view'])
                ->where('organizationContext.entitlements.limits', ['locations' => 5, 'seats' => null]),
        );
});

test('a guest receives no organization billing context', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('organizationContext.active', null)
                ->where('organizationContext.subscription', null)
                ->where('organizationContext.entitlements', null),
        );
});

test('the shared subscription context never exposes stripe secrets, customer, or payment data', function () {
    subscriptionContextFixturePlans();

    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create();

    createSubscriptionContextOrganizationSubscription($organization, [
        'stripe_status' => 'active',
        'ends_at' => Carbon::now()->addDays(10),
    ]);

    $response = $this->withSession(['active_organization_id' => $organization->id])
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('organizationContext.subscription', fn (Assert $subscription) => $subscription
                    ->hasAll([
                        'plan',
                        'status',
                        'accessMode',
                        'onTrial',
                        'trialEndsAt',
                        'endsAt',
                        'billingWarning',
                    ])
                    ->etc()
                ),
        );

    $props = $response->viewData('page')['props'];

    expect($props)
        ->not->toHaveKey('stripeKey')
        ->and($props['organizationContext']['subscription'])
        ->not->toHaveKeys([
            'stripe_id',
            'stripeId',
            'customerId',
            'stripeCustomerId',
            'secret',
            'webhookSecret',
            'paymentMethod',
        ]);

    $encoded = json_encode($props);

    expect($encoded)
        ->not->toContain('stripe_secret')
        ->and($encoded)
        ->not->toContain('webhook_secret')
        ->and($encoded)
        ->not->toContain('sub_');
});
