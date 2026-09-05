<?php

use App\Enums\BillingProvider;
use App\Enums\OrganizationRole;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Billing\FeatureCode;
use App\Support\Billing\MemberAIAccessResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'providers' => [
                'stripe' => ['monthly' => 'price_ai_test', 'yearly' => null],
                'paymongo' => ['monthly' => 'plan_ai_test', 'yearly' => null],
            ],
            'features' => ['ai.assistant'],
            'limits' => [],
        ],
    ]);
});

test('AI is declared by the feature-code authority and every paid plan', function () {
    expect(FeatureCode::all())->toContain(FeatureCode::Assistant)
        ->and(config('subscription.plans.starter.features'))->toContain(FeatureCode::Assistant)
        ->and(config('subscription.plans.growth.features'))->toContain(FeatureCode::Assistant)
        ->and(config('subscription.plans.business.features'))->toContain(FeatureCode::Assistant);
});

function createAIAccessSubscription(
    Organization $organization,
    array $attributes = [],
): BillingSubscription {
    $provider = $attributes['provider'] ?? BillingProvider::Stripe;
    unset($attributes['provider']);

    $customer = BillingCustomer::factory()
        ->for($organization)
        ->create(['provider' => $provider]);

    return BillingSubscription::factory()
        ->for($customer, 'billingCustomer')
        ->create(array_merge([
            'organization_id' => $organization->id,
            'billing_customer_id' => $customer->id,
            'provider' => $provider,
            'type' => config('billing.subscription_type'),
            'external_plan_id' => $provider === BillingProvider::Stripe
                ? 'price_ai_test'
                : 'plan_ai_test',
            'plan_code' => 'starter',
            'interval' => 'monthly',
            'provider_status' => 'active',
        ], $attributes));
}

test('a trial owner may use AI even when the persisted member flag is disabled', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => Carbon::now()->addDay(),
    ]);
    $membership = OrganizationMembership::factory()
        ->for($organization)
        ->for(User::factory())
        ->create([
            'role' => OrganizationRole::Owner,
            'ai_enabled' => false,
        ]);

    $access = MemberAIAccessResolver::resolve($organization, $membership);

    expect($access->featureGranted)->toBeTrue()
        ->and($access->memberEnabled)->toBeTrue()
        ->and($access->canUse())->toBeTrue();
});

test('a paid writable owner may use AI', function () {
    $organization = Organization::factory()->create([
        'rollout_classification' => null,
        'trial_ends_at' => null,
    ]);
    $membership = OrganizationMembership::factory()
        ->for($organization)
        ->for(User::factory())
        ->create(['role' => OrganizationRole::Owner]);
    createAIAccessSubscription($organization);

    expect(MemberAIAccessResolver::resolve($organization->fresh(), $membership)
        ->canUse())->toBeTrue();
});

test('a non-owner needs explicit AI enablement', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => Carbon::now()->addDay(),
    ]);
    $membership = OrganizationMembership::factory()
        ->for($organization)
        ->for(User::factory())
        ->create([
            'role' => OrganizationRole::Manager,
            'ai_enabled' => false,
        ]);

    expect(MemberAIAccessResolver::resolve($organization, $membership)
        ->canUse())->toBeFalse();

    $membership->update(['ai_enabled' => true]);

    expect(MemberAIAccessResolver::resolve($organization, $membership)
        ->canUse())->toBeTrue();
});

test('unpaid and ended access deny AI regardless of feature and member enablement', function (array $subscriptionAttributes): void {
    $organization = Organization::factory()->create([
        'rollout_classification' => null,
        'trial_ends_at' => null,
    ]);
    $membership = OrganizationMembership::factory()
        ->for($organization)
        ->for(User::factory())
        ->create([
            'role' => OrganizationRole::Manager,
            'ai_enabled' => true,
        ]);
    createAIAccessSubscription($organization, $subscriptionAttributes);

    $access = MemberAIAccessResolver::resolve($organization->fresh(), $membership);

    expect($access->featureGranted)->toBeTrue()
        ->and($access->memberEnabled)->toBeTrue()
        ->and($access->commerciallyWritable)->toBeFalse()
        ->and($access->canUse())->toBeFalse();
})->with([
    'unpaid' => [['provider_status' => 'unpaid']],
    'ended' => [[
        'provider_status' => 'cancelled',
        'ends_at' => Carbon::now()->subDay(),
    ]],
]);

test('an administratively inactive organization denies AI without changing commercial access', function () {
    $organization = Organization::factory()->create([
        'active' => false,
        'trial_ends_at' => Carbon::now()->addDay(),
    ]);
    $membership = OrganizationMembership::factory()
        ->for($organization)
        ->for(User::factory())
        ->create(['role' => OrganizationRole::Owner]);

    $access = MemberAIAccessResolver::resolve($organization, $membership);

    expect($access->commerciallyWritable)->toBeTrue()
        ->and($access->organizationActive)->toBeFalse()
        ->and($access->canUse())->toBeFalse();
});
