<?php

use App\Enums\OrganizationAccessMode;
use App\Models\Organization;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Support\Carbon;

function createOrganizationSubscription(Organization $organization, array $attributes = []): void
{
    $organization->subscriptions()->create(array_merge([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
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
        'stripe_status' => 'trialing',
        'trial_ends_at' => Carbon::now()->addDays(3),
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::Writable)
        ->and($access->onTrial)->toBeTrue()
        ->and($access->subscriptionStatus)->toBe('trialing');
});

test('an active subscription is writable', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'stripe_status' => 'active',
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
        'stripe_status' => 'past_due',
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
        'stripe_status' => 'active',
        'ends_at' => Carbon::now()->addDays(10),
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::Writable)
        ->and($access->onGracePeriod)->toBeTrue()
        ->and($access->billingWarning)->toBeFalse();
});

test('a canceled subscription past its grace period is read-only', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'stripe_status' => 'canceled',
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
        'stripe_status' => 'unpaid',
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->accessMode)->toBe(OrganizationAccessMode::ReadOnly)
        ->and($access->isReadOnly())->toBeTrue()
        ->and($access->billingWarning)->toBeFalse();
});

test('resolving the same organization state repeatedly yields identical results', function () {
    $organization = Organization::factory()->create();

    createOrganizationSubscription($organization, [
        'stripe_status' => 'past_due',
    ]);

    $organization = $organization->fresh();

    $first = OrganizationSubscriptionAccessResolver::resolve($organization);
    $second = OrganizationSubscriptionAccessResolver::resolve($organization);

    expect($first)->toEqual($second);
});
