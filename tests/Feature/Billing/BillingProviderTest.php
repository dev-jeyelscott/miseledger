<?php

use App\Enums\BillingProvider;
use App\Support\Billing\BillingConfigurationValidator;
use App\Support\Billing\BillingIdentity;

test('BillingProvider carries exactly the stripe and paymongo values', function () {
    expect(array_map(fn (BillingProvider $case): string => $case->value, BillingProvider::cases()))
        ->toBe(['stripe', 'paymongo']);
});

test('BillingIdentity converts a supported raw provider string to its enum case', function () {
    expect(BillingIdentity::provider('stripe'))->toBe(BillingProvider::Stripe)
        ->and(BillingIdentity::provider('paymongo'))->toBe(BillingProvider::PayMongo);
});

test('BillingIdentity returns null for an unsupported or malformed provider string', function () {
    expect(BillingIdentity::provider('unknown'))->toBeNull()
        ->and(BillingIdentity::provider(''))->toBeNull()
        ->and(BillingIdentity::provider('Stripe'))->toBeNull();
});

test('BillingConfigurationValidator still rejects an unsupported provider string after the enum refactor', function () {
    $configuration = [
        'provider' => 'square',
        'providers' => [
            'stripe' => ['enabled' => false],
            'paymongo' => ['enabled' => false],
        ],
        'required_in_production' => [],
    ];

    expect(fn () => BillingConfigurationValidator::validateProduction($configuration))
        ->toThrow(RuntimeException::class, 'Production billing configuration requires BILLING_PROVIDER to be explicitly set to stripe or paymongo.');
});
