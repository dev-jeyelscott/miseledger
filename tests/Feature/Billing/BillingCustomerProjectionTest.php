<?php

use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\Organization;
use Illuminate\Database\QueryException;

test('a billing customer casts provider and livemode and resolves its organization and subscriptions', function () {
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create([
        'provider' => BillingProvider::Stripe,
        'external_customer_id' => 'cus_projection_casts',
        'livemode' => true,
    ]);

    expect($customer->provider)->toBe(BillingProvider::Stripe)
        ->and($customer->livemode)->toBeTrue()
        ->and($customer->organization)->toBeInstanceOf(Organization::class)
        ->and($customer->organization->is($organization))->toBeTrue()
        ->and($customer->subscriptions)->toHaveCount(0);
});

test('an organization may hold separate customer identities per provider', function () {
    $organization = Organization::factory()->create();

    BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::Stripe]);
    $paymongoCustomer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);

    expect($organization->billingCustomers()->count())->toBe(2)
        ->and($paymongoCustomer->provider)->toBe(BillingProvider::PayMongo);
});

test('the same organization cannot hold two customer rows on the same provider', function () {
    $organization = Organization::factory()->create();

    BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::Stripe]);

    expect(fn () => BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::Stripe]))
        ->toThrow(QueryException::class);
});

test('the same external customer id cannot map to two organizations on the same provider', function () {
    BillingCustomer::factory()->create([
        'provider' => BillingProvider::Stripe,
        'external_customer_id' => 'cus_shared_identity',
    ]);

    expect(fn () => BillingCustomer::factory()->create([
        'provider' => BillingProvider::Stripe,
        'external_customer_id' => 'cus_shared_identity',
    ]))->toThrow(QueryException::class);
});

test('the same external customer id may be reused across different providers', function () {
    BillingCustomer::factory()->create([
        'provider' => BillingProvider::Stripe,
        'external_customer_id' => 'cus_cross_provider',
    ]);

    $paymongoCustomer = BillingCustomer::factory()->create([
        'provider' => BillingProvider::PayMongo,
        'external_customer_id' => 'cus_cross_provider',
    ]);

    expect($paymongoCustomer->exists)->toBeTrue();
});
