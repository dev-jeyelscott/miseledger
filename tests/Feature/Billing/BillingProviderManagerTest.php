<?php

use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\Providers\BillingProviderManager;
use App\Support\Billing\Providers\StripeBillingProvider;
use Illuminate\Support\Facades\Config;

test('defaultProvider resolves the configured acquisition provider', function () {
    Config::set('billing.provider', 'stripe');

    expect(app(BillingProviderManager::class)->defaultProvider())->toBe(BillingProvider::Stripe);
});

test('defaultProvider fails closed when unset, blank, or unsupported', function (mixed $value) {
    Config::set('billing.provider', $value);

    expect(fn () => app(BillingProviderManager::class)->defaultProvider())
        ->toThrow(RuntimeException::class, 'BILLING_PROVIDER to be explicitly set to stripe or paymongo');
})->with([
    'null' => [null],
    'blank' => [''],
    'unsupported' => ['square'],
]);

test('provider resolves the implemented Stripe adapter', function () {
    $provider = app(BillingProviderManager::class)->provider(BillingProvider::Stripe);

    expect($provider)->toBeInstanceOf(StripeBillingProvider::class)
        ->and($provider->identity())->toBe(BillingProvider::Stripe);
});

test('provider reports paymongo as unavailable rather than falling back to Stripe', function () {
    expect(fn () => app(BillingProviderManager::class)->provider(BillingProvider::PayMongo))
        ->toThrow(RuntimeException::class, 'paymongo billing provider is not yet available');
});

test('providerForOrganization resolves the persisted provider for an existing subscription', function () {
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::Stripe]);
    BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::Stripe,
    ]);

    $provider = app(BillingProviderManager::class)->providerForOrganization($organization);

    expect($provider->identity())->toBe(BillingProvider::Stripe);
});

test('providerForOrganization falls back to defaultProvider when no subscription exists yet', function () {
    Config::set('billing.provider', 'stripe');

    $organization = Organization::factory()->create();

    $provider = app(BillingProviderManager::class)->providerForOrganization($organization);

    expect($provider->identity())->toBe(BillingProvider::Stripe);
});

test('providerForOrganization fails closed on ambiguous multi-provider ownership', function () {
    $organization = Organization::factory()->create();

    $stripeCustomer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::Stripe]);
    $paymongoCustomer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);

    BillingSubscription::factory()->for($stripeCustomer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::Stripe,
    ]);
    BillingSubscription::factory()->for($paymongoCustomer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
    ]);

    expect(fn () => app(BillingProviderManager::class)->providerForOrganization($organization))
        ->toThrow(RuntimeException::class, 'ambiguous billing provider ownership');
});
