<?php

use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

test('a billing subscription casts provider, livemode, and its datetime columns and resolves its relations', function () {
    $customer = BillingCustomer::factory()->create(['provider' => BillingProvider::Stripe]);
    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $customer->organization_id,
        'provider' => BillingProvider::Stripe,
        'livemode' => true,
        'trial_ends_at' => now()->addDays(14),
    ]);

    expect($subscription->provider)->toBe(BillingProvider::Stripe)
        ->and($subscription->livemode)->toBeTrue()
        ->and($subscription->trial_ends_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($subscription->organization->is($customer->organization))->toBeTrue()
        ->and($subscription->billingCustomer->is($customer))->toBeTrue();
});

test('the same provider cannot reuse an external subscription id', function () {
    $customer = BillingCustomer::factory()->create(['provider' => BillingProvider::Stripe]);

    BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $customer->organization_id,
        'provider' => BillingProvider::Stripe,
        'external_subscription_id' => 'sub_duplicate',
    ]);

    expect(fn () => BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $customer->organization_id,
        'provider' => BillingProvider::Stripe,
        'external_subscription_id' => 'sub_duplicate',
    ]))->toThrow(QueryException::class);
});

test('the same raw external subscription id may be reused across different providers', function () {
    $stripeCustomer = BillingCustomer::factory()->create(['provider' => BillingProvider::Stripe]);
    $paymongoCustomer = BillingCustomer::factory()->create(['provider' => BillingProvider::PayMongo]);

    BillingSubscription::factory()->for($stripeCustomer, 'billingCustomer')->create([
        'organization_id' => $stripeCustomer->organization_id,
        'provider' => BillingProvider::Stripe,
        'external_subscription_id' => 'sub_cross_provider',
    ]);

    $paymongoSubscription = BillingSubscription::factory()->for($paymongoCustomer, 'billingCustomer')->create([
        'organization_id' => $paymongoCustomer->organization_id,
        'provider' => BillingProvider::PayMongo,
        'external_subscription_id' => 'sub_cross_provider',
    ]);

    expect($paymongoSubscription->exists)->toBeTrue();
});

test('a subscription cannot be attached to a billing customer from a different organization', function () {
    $customer = BillingCustomer::factory()->create(['provider' => BillingProvider::Stripe]);
    $otherOrganization = Organization::factory()->create();

    expect(fn () => BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $otherOrganization->getKey(),
        'provider' => BillingProvider::Stripe,
    ]))->toThrow(QueryException::class);
});

test('a subscription cannot be attached to a billing customer under a different provider', function () {
    $customer = BillingCustomer::factory()->create(['provider' => BillingProvider::Stripe]);

    expect(fn () => BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $customer->organization_id,
        'provider' => BillingProvider::PayMongo,
    ]))->toThrow(QueryException::class);
});
