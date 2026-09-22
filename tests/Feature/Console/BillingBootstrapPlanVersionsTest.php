<?php

use App\Models\BillingPlanVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

function bootstrapFixturePlans(): array
{
    return [
        'starter' => [
            'name' => 'Starter Plan',
            'tier' => 1,
            'manual_amounts' => [
                'monthly' => 49_900,
                'yearly' => null,
            ],
            'providers' => [
                'stripe' => [
                    'monthly' => 'price_starter_monthly',
                    'yearly' => null,
                ],
            ],
            'features' => [],
            'limits' => ['seats' => 3, 'locations' => 1, 'inventory_items' => 500],
        ],
    ];
}

beforeEach(function (): void {
    Config::set('billing.plans', bootstrapFixturePlans());
    Config::set('billing.currency', 'PHP');
});

test('dry run reports the planned draft without writing to the database', function () {
    $this->artisan('billing:bootstrap-plan-versions')
        ->expectsOutputToContain('Dry run only. No database changes will be written.')
        ->expectsOutputToContain('Would create starter v1 draft.')
        ->expectsOutputToContain('Would record paymongo manual monthly: 49900 PHP')
        ->expectsOutputToContain('Missing authoritative numeric stripe automatic monthly price in PHP. Populate before publication.')
        ->assertSuccessful();

    expect(BillingPlanVersion::query()->count())->toBe(0);
});

test('apply persists only the locally authoritative manual price and never fabricates the missing automatic price', function () {
    $this->artisan('billing:bootstrap-plan-versions', ['--apply' => true])
        ->assertSuccessful();

    $version = BillingPlanVersion::query()
        ->where('plan_code', 'starter')
        ->where('version', 1)
        ->with('prices')
        ->firstOrFail();

    expect($version->name)->toBe('Starter Plan')
        ->and($version->tier)->toBe(1)
        ->and($version->prices)->toHaveCount(1)
        ->and($version->prices->first()->provider->value)->toBe('paymongo')
        ->and($version->prices->first()->collection_method->value)->toBe('manual')
        ->and($version->prices->first()->currency)->toBe('PHP')
        ->and($version->prices->first()->amount_minor)->toBe(49_900);
});

test('repeated dry run and apply are idempotent and report existing coverage gaps', function () {
    $this->artisan('billing:bootstrap-plan-versions', ['--apply' => true])
        ->assertSuccessful();

    expect(BillingPlanVersion::query()->where('plan_code', 'starter')->count())->toBe(1);

    $this->artisan('billing:bootstrap-plan-versions', ['--apply' => true])
        ->expectsOutputToContain('starter v1 already exists. No change.')
        ->expectsOutputToContain('Missing authoritative numeric stripe automatic monthly price in PHP. Populate before publication.')
        ->assertSuccessful();

    expect(BillingPlanVersion::query()->where('plan_code', 'starter')->count())->toBe(1);

    $this->artisan('billing:bootstrap-plan-versions')
        ->expectsOutputToContain('starter v1 already exists. No change.')
        ->assertSuccessful();
});

test('an unresolved billing currency is reported as missing rather than defaulted', function () {
    Config::set('billing.currency', null);

    $this->artisan('billing:bootstrap-plan-versions')
        ->expectsOutputToContain('Missing authoritative billing currency for paymongo manual monthly price. Configure SUBSCRIPTION_BILLING_CURRENCY before publication.')
        ->assertSuccessful();

    $this->artisan('billing:bootstrap-plan-versions', ['--apply' => true])
        ->assertSuccessful();

    $version = BillingPlanVersion::query()
        ->where('plan_code', 'starter')
        ->where('version', 1)
        ->with('prices')
        ->firstOrFail();

    expect($version->prices)->toHaveCount(0);
});

test('command output never contains a provider secret or external plan identifier', function () {
    Config::set('billing.providers.stripe.secret', 'sk_live_super_secret_value');
    Config::set('billing.providers.paymongo.secret_key', 'sk_live_another_secret');

    Artisan::call('billing:bootstrap-plan-versions');

    $output = Artisan::output();

    expect($output)
        ->toContain('starter')
        ->not->toContain('sk_live_super_secret_value')
        ->not->toContain('sk_live_another_secret')
        ->not->toContain('price_starter_monthly');
});
