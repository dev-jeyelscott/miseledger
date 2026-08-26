<?php

use App\Enums\BillingProvider;
use App\Enums\OrganizationAccessMode;
use App\Enums\OrganizationRolloutClassification;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'providers' => [
                'stripe' => ['monthly' => 'price_test', 'yearly' => null],
                'paymongo' => ['monthly' => 'plan_test', 'yearly' => null],
            ],
            'features' => [],
            'limits' => [],
        ],
    ]);
});

function createOrganizationSubscription(Organization $organization, array $attributes = []): BillingSubscription
{
    $provider = $attributes['provider'] ?? BillingProvider::Stripe;
    unset($attributes['provider']);
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => $provider]);

    return BillingSubscription::factory()->for($customer, 'billingCustomer')->create(array_merge([
        'organization_id' => $organization->getKey(),
        'billing_customer_id' => $customer->getKey(),
        'provider' => $provider,
        'type' => config('billing.subscription_type'),
        'external_plan_id' => $provider === BillingProvider::Stripe ? 'price_test' : 'plan_test',
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'provider_status' => 'active',
    ], $attributes));
}

test('a generic trial without a subscription is writable and on trial', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => Carbon::now()->addDays(5),
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization);

    expect($access->accessMode)->toBe(OrganizationAccessMode::Writable)
        ->and($access->isWritable())->toBeTrue()
        ->and($access->onTrial)->toBeTrue()
        ->and($access->onGracePeriod)->toBeFalse()
        ->and($access->billingWarning)->toBeFalse()
        ->and($access->subscriptionStatus)->toBeNull();
});

test('an expired generic trial without a subscription is read-only', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => Carbon::now()->subDay(),
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization);

    expect($access->accessMode)->toBe(OrganizationAccessMode::ReadOnly)
        ->and($access->isReadOnly())->toBeTrue()
        ->and($access->onTrial)->toBeFalse();
});

test('a subscription trialing on stripe is writable and on trial', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'provider_status' => 'trialing',
        'trial_ends_at' => Carbon::now()->addDays(3),
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::Writable)
        ->and($access->onTrial)->toBeTrue()
        ->and($access->subscriptionStatus)->toBe('trial');
});

test('an active subscription is writable', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'provider_status' => 'active',
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::Writable)
        ->and($access->onTrial)->toBeFalse()
        ->and($access->onGracePeriod)->toBeFalse()
        ->and($access->billingWarning)->toBeFalse()
        ->and($access->subscriptionStatus)->toBe('active');
});

test('a past due subscription is writable with a billing warning', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'provider_status' => 'past_due',
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::Writable)
        ->and($access->isWritable())->toBeTrue()
        ->and($access->billingWarning)->toBeTrue()
        ->and($access->subscriptionStatus)->toBe('past_due');
});

test('a subscription scheduled to cancel remains writable during its paid grace period', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'provider_status' => 'active',
        'ends_at' => Carbon::now()->addDays(10),
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::Writable)
        ->and($access->onGracePeriod)->toBeTrue()
        ->and($access->billingWarning)->toBeTrue();
});

test('a canceled subscription past its grace period is read-only', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'provider_status' => 'cancelled',
        'ends_at' => Carbon::now()->subDay(),
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::ReadOnly)
        ->and($access->isReadOnly())->toBeTrue()
        ->and($access->onGracePeriod)->toBeFalse();
});

test('an unpaid subscription is read-only', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'provider_status' => 'unpaid',
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::ReadOnly)
        ->and($access->isReadOnly())->toBeTrue()
        ->and($access->billingWarning)->toBeFalse();
});

test('an unpaid subscription with a future ends_at remains read-only', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'provider_status' => 'unpaid',
        'ends_at' => Carbon::now()->addDays(10),
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::ReadOnly)
        ->and($access->isReadOnly())->toBeTrue()
        ->and($access->billingWarning)->toBeFalse();
});

test('resolving the same organization state repeatedly yields identical results', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'provider_status' => 'past_due',
    ]);

    $organization = $organization->fresh();

    $first = OrganizationSubscriptionAccessResolver::resolve($organization);
    $second = OrganizationSubscriptionAccessResolver::resolve($organization);

    expect($first)->toEqual($second);
});

test('an unclassified legacy organization with no trial and no durable billing customer stays writable', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => null,
        'rollout_classification' => null,
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization);

    expect($access->accessMode)->toBe(OrganizationAccessMode::Writable)
        ->and($access->isWritable())->toBeTrue()
        ->and($access->onTrial)->toBeFalse();
});

test('a legacy organization with a durable billing customer and no subscription remains read-only pending sync', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => null,
        'rollout_classification' => null,
    ]);
    BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::Stripe]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization);

    expect($access->accessMode)->toBe(OrganizationAccessMode::ReadOnly);
});

test('development_test, internal_free, and grandfathered classifications are permanently writable', function (OrganizationRolloutClassification $classification) {
    $organization = Organization::factory()->create([
        'trial_ends_at' => Carbon::now()->subDay(),
        'rollout_classification' => $classification,
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization);

    expect($access->accessMode)->toBe(OrganizationAccessMode::Writable);
})->with([
    OrganizationRolloutClassification::DevelopmentTest,
    OrganizationRolloutClassification::InternalFree,
    OrganizationRolloutClassification::Grandfathered,
]);

test('a trial_eligible classification is derived from trial/subscription state like a normal tenant', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => Carbon::now()->subDay(),
        'rollout_classification' => OrganizationRolloutClassification::TrialEligible,
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization);

    expect($access->accessMode)->toBe(OrganizationAccessMode::ReadOnly);
});

test('an immediately_billable classification is derived from subscription state like a normal tenant', function () {
    $organization = Organization::factory()->create([
        'trial_ends_at' => null,
        'rollout_classification' => OrganizationRolloutClassification::ImmediatelyBillable,
    ]);

    createOrganizationSubscription($organization, ['provider' => BillingProvider::PayMongo]);

    expect(OrganizationSubscriptionAccessResolver::resolve($organization)->accessMode)
        ->toBe(OrganizationAccessMode::Writable);
});

test('Stripe and PayMongo projections resolve equivalent provider-neutral access', function (BillingProvider $provider, string $status, OrganizationAccessMode $mode, bool $warning) {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'provider' => $provider,
        'provider_status' => $status,
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization);

    expect($access->accessMode)->toBe($mode)
        ->and($access->billingWarning)->toBe($warning);
})->with([
    'Stripe active' => [BillingProvider::Stripe, 'active', OrganizationAccessMode::Writable, false],
    'PayMongo active' => [BillingProvider::PayMongo, 'active', OrganizationAccessMode::Writable, false],
    'Stripe past due' => [BillingProvider::Stripe, 'past_due', OrganizationAccessMode::Writable, true],
    'PayMongo past due' => [BillingProvider::PayMongo, 'past_due', OrganizationAccessMode::Writable, true],
    'Stripe unpaid' => [BillingProvider::Stripe, 'unpaid', OrganizationAccessMode::ReadOnly, false],
    'PayMongo unpaid' => [BillingProvider::PayMongo, 'unpaid', OrganizationAccessMode::ReadOnly, false],
]);

test('a cancellation remains writable immediately before and becomes read-only after its local paid-access end time', function () {
    $organization = Organization::factory()->create();
    $subscription = createOrganizationSubscription($organization, [
        'provider' => BillingProvider::PayMongo,
        'provider_status' => 'cancelled',
        'ends_at' => now()->addSecond(),
        'cancelled_at' => now(),
    ]);

    expect(OrganizationSubscriptionAccessResolver::resolve($organization)->accessMode)
        ->toBe(OrganizationAccessMode::Writable);

    $subscription->update(['ends_at' => now()->subSecond()]);

    expect(OrganizationSubscriptionAccessResolver::resolve($organization)->accessMode)
        ->toBe(OrganizationAccessMode::ReadOnly);
});

test('ambiguous subscription ownership fails closed and does not use the selected acquisition provider', function () {
    Config::set('billing.provider', 'paymongo');
    $organization = Organization::factory()->create();
    createOrganizationSubscription($organization, ['provider' => BillingProvider::Stripe]);
    createOrganizationSubscription($organization, ['provider' => BillingProvider::PayMongo]);

    expect(OrganizationSubscriptionAccessResolver::resolve($organization)->accessMode)
        ->toBe(OrganizationAccessMode::ReadOnly);
});

test('commercial resolution remains independent from administrative organization activity', function () {
    $organization = Organization::factory()->create(['active' => false]);
    createOrganizationSubscription($organization, ['provider' => BillingProvider::PayMongo]);

    expect(OrganizationSubscriptionAccessResolver::resolve($organization)->accessMode)
        ->toBe(OrganizationAccessMode::Writable);
});
