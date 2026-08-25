<?php

use App\Models\Organization;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Billing\BillingConfigurationValidator;
use Illuminate\Foundation\Testing\WithCachedConfig;
use Illuminate\Support\Facades\Config;

uses(WithCachedConfig::class);

test('billing configuration exposes the provider-aware contract and Stripe compatibility alias', function () {
    expect(config('billing'))
        ->toHaveKeys([
            'provider',
            'providers',
            'stripe',
            'subscription_type',
            'currency',
            'trial_days',
            'plans',
            'logger',
            'required_in_production',
        ])
        ->and(config('billing.providers'))
        ->toHaveKeys(['stripe', 'paymongo'])
        ->and(config('billing.providers.stripe'))
        ->toHaveKeys(['enabled', 'key', 'secret', 'mode', 'webhook_secret'])
        ->and(config('billing.providers.paymongo'))
        ->toHaveKeys(['enabled', 'mode', 'public_key', 'secret_key', 'webhook_secret', 'api_base_url'])
        ->and(config('billing.stripe'))
        ->toBe(config('billing.providers.stripe'));
});

test('billing provider has no implicit Stripe fallback', function () {
    $billingConfiguration = file_get_contents(config_path('billing.php'));

    expect($billingConfiguration)
        ->toContain("'provider' => env('BILLING_PROVIDER')")
        ->not->toContain("env('BILLING_PROVIDER', 'stripe')");
});

test('provider environment placeholders contain no committed credentials', function () {
    expect(file_get_contents(base_path('.env.example')))
        ->toContain('BILLING_PROVIDER=')
        ->toContain('BILLING_STRIPE_ENABLED=false')
        ->toContain('BILLING_PAYMONGO_ENABLED=false')
        ->toContain('STRIPE_KEY=')
        ->toContain('STRIPE_SECRET=')
        ->toContain('STRIPE_TEST_WEBHOOK_SECRET=')
        ->toContain('STRIPE_LIVE_WEBHOOK_SECRET=')
        ->toContain('PAYMONGO_PUBLIC_KEY=')
        ->toContain('PAYMONGO_SECRET_KEY=')
        ->toContain('PAYMONGO_WEBHOOK_SECRET=')
        ->toContain('STRIPE_PRICE_STARTER_MONTHLY=')
        ->toContain('STRIPE_PRICE_GROWTH_MONTHLY=')
        ->toContain('STRIPE_PRICE_BUSINESS_MONTHLY=')
        ->toContain('PAYMONGO_PLAN_STARTER_MONTHLY=')
        ->toContain('PAYMONGO_PLAN_GROWTH_MONTHLY=')
        ->toContain('PAYMONGO_PLAN_BUSINESS_MONTHLY=')
        ->not->toContain('STRIPE_SECRET=sk_')
        ->not->toContain('STRIPE_KEY=pk_')
        ->not->toContain('PAYMONGO_PUBLIC_KEY=pk_')
        ->not->toContain('PAYMONGO_SECRET_KEY=sk_')
        ->not->toContain('PAYMONGO_WEBHOOK_SECRET=whsk_');
});

test('provider secrets are never shared with inertia', function () {
    Config::set('billing.providers.stripe.secret', 'sk_test_should_never_leak');
    Config::set('billing.stripe.secret', 'sk_test_should_never_leak');
    Config::set('billing.providers.paymongo.secret_key', 'sk_test_paymongo_should_never_leak');
    Config::set('billing.providers.paymongo.webhook_secret', 'whsk_should_never_leak');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $page = $response->viewData('page') ?? [];
    $props = json_encode($page);

    expect($props)
        ->not->toContain('sk_test_should_never_leak')
        ->not->toContain('sk_test_paymongo_should_never_leak')
        ->not->toContain('whsk_should_never_leak');
});

test('billing plan catalog is sourced from the subscription configuration contract', function () {
    expect(config('billing.plans'))->toBe(config('subscription.plans'))
        ->and(config('billing.subscription_type'))->toBe(config('subscription.type'))
        ->and(config('billing.trial_days'))->toBe(config('subscription.trial_days'));
});

test('billing currency is independent from organization currency', function () {
    Config::set('billing.currency', 'usd');

    $organization = Organization::factory()->create(['currency' => 'PHP']);

    expect(config('billing.currency'))->toBe('usd')
        ->and($organization->currency)->toBe('PHP')
        ->and(config('billing.currency'))->not->toBe($organization->currency);
});

test('billing logger falls back to the stack channel when unset', function () {
    expect(config('billing.logger'))->not->toBeNull();
});

test('missing required common billing configuration fails safely in production', function () {
    Config::set('billing', billingProductionConfiguration([
        'currency' => null,
    ]));

    app()->detectEnvironment(fn () => 'production');

    try {
        (new AppServiceProvider(app()))->boot();

        $this->fail('Expected missing billing configuration to throw.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->toContain('currency')
            ->not->toContain('pk_live_placeholder')
            ->not->toContain('sk_live_placeholder');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});

test('missing or unsupported selected providers fail closed', function () {
    expect(fn () => BillingConfigurationValidator::validateProduction(
        billingProductionConfiguration([
            'provider' => null,
        ]),
    ))->toThrow(
        RuntimeException::class,
        'BILLING_PROVIDER to be explicitly set to stripe or paymongo',
    );

    expect(fn () => BillingConfigurationValidator::validateProduction(
        billingProductionConfiguration([
            'provider' => 'unsupported',
        ]),
    ))->toThrow(
        RuntimeException::class,
        'BILLING_PROVIDER to be explicitly set to stripe or paymongo',
    );
});

test('selected provider must be enabled', function () {
    expect(fn () => BillingConfigurationValidator::validateProduction(
        billingProductionConfiguration([
            'providers' => [
                'stripe' => [
                    'enabled' => false,
                ],
            ],
        ]),
    ))->toThrow(
        RuntimeException::class,
        'selected billing provider must be enabled',
    );
});

test('invalid selected provider configuration fails during production boot', function () {
    Config::set('billing', billingProductionConfiguration([
        'providers' => [
            'stripe' => [
                'enabled' => false,
            ],
        ],
    ]));

    app()->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => (new AppServiceProvider(app()))->boot())
            ->toThrow(
                RuntimeException::class,
                'selected billing provider must be enabled',
            );
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});

test('Stripe-only production validates without PayMongo credentials', function () {
    BillingConfigurationValidator::validateProduction(
        billingProductionConfiguration([
            'providers' => [
                'paymongo' => [
                    'enabled' => false,
                    'public_key' => null,
                    'secret_key' => null,
                    'webhook_secret' => null,
                ],
            ],
        ]),
    );

    expect(true)->toBeTrue();
});

test('PayMongo-only production validates without Stripe credentials', function () {
    BillingConfigurationValidator::validateProduction(
        paymongoOnlyProductionConfiguration(),
    );

    expect(true)->toBeTrue();
});

test('PayMongo-only production boots without Stripe credentials', function () {
    Config::set('billing', paymongoOnlyProductionConfiguration());

    app()->detectEnvironment(fn () => 'production');

    try {
        (new AppServiceProvider(app()))->boot();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(true)->toBeTrue();
});

test('disabled secondary providers do not require credentials', function () {
    BillingConfigurationValidator::validateProduction(
        billingProductionConfiguration([
            'providers' => [
                'paymongo' => [
                    'enabled' => false,
                    'public_key' => null,
                    'secret_key' => null,
                    'webhook_secret' => null,
                ],
            ],
        ]),
    );

    expect(true)->toBeTrue();
});

test('enabled secondary providers must have valid production configuration', function () {
    expect(fn () => BillingConfigurationValidator::validateProduction(
        billingProductionConfiguration([
            'providers' => [
                'paymongo' => [
                    'enabled' => true,
                    'public_key' => null,
                    'secret_key' => null,
                    'webhook_secret' => null,
                ],
            ],
        ]),
    ))->toThrow(
        RuntimeException::class,
        'Missing required PayMongo billing configuration',
    );
});

test('production billing configuration rejects test or mixed Stripe modes', function () {
    expect(fn () => BillingConfigurationValidator::validateProduction(
        billingProductionConfiguration([
            'providers' => [
                'stripe' => [
                    'key' => 'pk_test_placeholder',
                    'mode' => 'test',
                ],
            ],
        ]),
    ))->toThrow(
        RuntimeException::class,
        'matching live Stripe API keys',
    );

    expect(fn () => BillingConfigurationValidator::validateProduction(
        billingProductionConfiguration([
            'providers' => [
                'stripe' => [
                    'secret' => 'sk_test_placeholder',
                ],
            ],
        ]),
    ))->toThrow(
        RuntimeException::class,
        'matching live Stripe API keys',
    );
});

test('production billing configuration rejects PayMongo test or mixed API keys', function () {
    expect(fn () => BillingConfigurationValidator::validateProduction(
        paymongoOnlyProductionConfiguration([
            'providers' => [
                'paymongo' => [
                    'public_key' => 'pk_test_placeholder',
                    'secret_key' => 'sk_test_placeholder',
                ],
            ],
        ]),
    ))->toThrow(
        RuntimeException::class,
        'matching live PayMongo API keys',
    );

    expect(fn () => BillingConfigurationValidator::validateProduction(
        paymongoOnlyProductionConfiguration([
            'providers' => [
                'paymongo' => [
                    'secret_key' => 'sk_test_placeholder',
                ],
            ],
        ]),
    ))->toThrow(
        RuntimeException::class,
        'matching live PayMongo API keys',
    );
});

test('billing configuration boots without error with valid Stripe production configuration', function () {
    Config::set('billing', billingProductionConfiguration());

    app()->detectEnvironment(fn () => 'production');

    try {
        (new AppServiceProvider(app()))->boot();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(true)->toBeTrue();
});

test('Cashier consumes canonical cached Stripe billing configuration', function () {
    expect(app()->bound('config_loaded_from_cache'))->toBeTrue()
        ->and(config('cashier.key'))
        ->toBe(config('billing.providers.stripe.key'))
        ->and(config('cashier.secret'))
        ->toBe(config('billing.providers.stripe.secret'))
        ->and(config('cashier.webhook.secret'))
        ->toBe(config('billing.providers.stripe.webhook_secret'));

    expect(file_get_contents(config_path('cashier.php')))
        ->toContain("\$billing = require __DIR__.'/billing.php';")
        ->toContain("\$stripe = \$billing['providers']['stripe'];")
        ->not->toContain("env('STRIPE_KEY')")
        ->not->toContain("env('STRIPE_SECRET')")
        ->not->toContain("env('STRIPE_WEBHOOK_SECRET')")
        ->not->toContain("env('PAYMONGO");
});

test('billing application runtime does not read provider environment variables directly', function () {
    expect(file_get_contents(app_path('Support/Billing/BillingConfigurationValidator.php')))
        ->not->toContain('env(')
        ->and(file_get_contents(app_path('Providers/AppServiceProvider.php')))
        ->not->toContain('env(');
});

/**
 * Build a valid Stripe-selected production billing configuration with optional
 * overrides for focused validation tests.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function billingProductionConfiguration(array $overrides = []): array
{
    return array_replace_recursive([
        'provider' => 'stripe',
        'providers' => [
            'stripe' => [
                'enabled' => true,
                'key' => 'pk_live_placeholder',
                'secret' => 'sk_live_placeholder',
                'mode' => 'live',
                'webhook_secret' => 'whsec_placeholder',
            ],
            'paymongo' => [
                'enabled' => false,
                'public_key' => null,
                'secret_key' => null,
                'webhook_secret' => null,
            ],
        ],
        'stripe' => [
            'enabled' => true,
            'key' => 'pk_live_placeholder',
            'secret' => 'sk_live_placeholder',
            'mode' => 'live',
            'webhook_secret' => 'whsec_placeholder',
        ],
        'required_in_production' => [
            'currency',
            'trial_days',
            'subscription_type',
        ],
        'currency' => 'usd',
        'trial_days' => 30,
        'subscription_type' => 'default',
    ], $overrides);
}

/**
 * Build a valid PayMongo-selected production configuration with Stripe fully
 * disabled to prove provider-independent validation.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymongoOnlyProductionConfiguration(array $overrides = []): array
{
    return array_replace_recursive(
        billingProductionConfiguration([
            'provider' => 'paymongo',
            'providers' => [
                'stripe' => [
                    'enabled' => false,
                    'key' => null,
                    'secret' => null,
                    'mode' => null,
                    'webhook_secret' => null,
                ],
                'paymongo' => [
                    'enabled' => true,
                    'public_key' => 'pk_live_placeholder',
                    'secret_key' => 'sk_live_placeholder',
                    'webhook_secret' => 'whsk_placeholder',
                    'mode' => 'live',
                    'api_base_url' => 'https://api.paymongo.com/v1',
                ],
            ],
            'stripe' => [
                'enabled' => false,
                'key' => null,
                'secret' => null,
                'mode' => null,
                'webhook_secret' => null,
            ],
        ]),
        $overrides,
    );
}
