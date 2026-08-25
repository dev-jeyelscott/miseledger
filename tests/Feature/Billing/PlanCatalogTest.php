<?php

use App\Enums\PlanCode;
use App\Support\Billing\PlanCatalog;
use Illuminate\Support\Facades\Config;

function planCatalogFixturePlans(): array
{
    return [
        'starter' => [
            'name' => 'Starter',
            'prices' => [
                'monthly' => 'price_starter_monthly',
                'yearly' => 'price_starter_yearly',
            ],
            'features' => ['inventory.view', 'inventory.adjust'],
            'limits' => ['locations' => 1, 'seats' => null],
        ],
        'pro' => [
            'name' => 'Pro',
            'prices' => [
                'monthly' => 'price_pro_monthly',
                'yearly' => 'price_pro_yearly',
            ],
            'features' => ['inventory.view', 'inventory.adjust', 'reports.view'],
            'limits' => ['locations' => null, 'seats' => null],
        ],
    ];
}

test('the configured plans provide the intended features and limits', function () {
    $catalog = new PlanCatalog(config('subscription.plans'));

    $starter = $catalog->get(PlanCode::from('starter'));
    $growth = $catalog->get(PlanCode::from('growth'));
    $business = $catalog->get(PlanCode::from('business'));

    expect($starter?->name)->toBe('Starter Plan')
        ->and($starter?->features)->toBe([])
        ->and($starter?->limits)->toBe([
            'seats' => 3,
            'locations' => 1,
            'inventory_items' => 500,
        ])
        ->and($growth?->name)->toBe('Growth Plan')
        ->and($growth?->features)->toBe([
            'purchasing',
            'recipes',
            'reports.export',
            'locations.multi',
        ])
        ->and($growth?->limits)->toBe([
            'seats' => 10,
            'locations' => 5,
            'inventory_items' => 5000,
        ])
        ->and($business?->name)->toBe('Business Plan')
        ->and($business?->features)->toBe($growth?->features)
        ->and($business?->limits)->toBe([
            'seats' => null,
            'locations' => null,
            'inventory_items' => null,
        ]);
});

test('a configured monthly price id resolves to exactly one plan definition', function () {
    $catalog = new PlanCatalog(planCatalogFixturePlans());

    $definition = $catalog->resolveByPriceId('price_starter_monthly');

    expect($definition)->not->toBeNull()
        ->and($definition->code->equals(PlanCode::from('starter')))->toBeTrue()
        ->and($definition->name)->toBe('Starter')
        ->and($definition->features)->toBe(['inventory.view', 'inventory.adjust'])
        ->and($definition->limit('locations'))->toBe(1)
        ->and($definition->limit('seats'))->toBeNull();
});

test('a configured yearly price id resolves to the same plan as its monthly counterpart', function () {
    $catalog = new PlanCatalog(planCatalogFixturePlans());

    $monthly = $catalog->resolveByPriceId('price_pro_monthly');
    $yearly = $catalog->resolveByPriceId('price_pro_yearly');

    expect($monthly)->not->toBeNull()
        ->and($yearly)->not->toBeNull()
        ->and($monthly->code->equals($yearly->code))->toBeTrue()
        ->and($catalog->resolveIntervalByPriceId('price_pro_monthly'))->toBe('monthly')
        ->and($catalog->resolveIntervalByPriceId('price_pro_yearly'))->toBe('yearly');
});

test('an unknown price id fails to resolve and grants no plan', function () {
    $catalog = new PlanCatalog(planCatalogFixturePlans());

    expect($catalog->resolveByPriceId('price_does_not_exist'))->toBeNull()
        ->and($catalog->resolveIntervalByPriceId('price_does_not_exist'))->toBeNull();
});

test('a missing price id for an interval never resolves', function () {
    $catalog = new PlanCatalog([
        'starter' => [
            'name' => 'Starter',
            'prices' => ['monthly' => 'price_starter_monthly', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);

    $definition = $catalog->get(PlanCode::from('starter'));

    expect($definition->priceId('yearly'))->toBeNull()
        ->and($catalog->resolveByPriceId(''))->toBeNull();
});

test('a duplicate price id across plans fails safely and resolves to no plan', function () {
    $catalog = new PlanCatalog([
        'starter' => [
            'name' => 'Starter',
            'prices' => ['monthly' => 'price_shared', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
        'pro' => [
            'name' => 'Pro',
            'prices' => ['monthly' => 'price_shared', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);

    expect($catalog->resolveByPriceId('price_shared'))->toBeNull();
});

test('an invalid plan code entry is excluded from the catalog rather than crashing resolution', function () {
    $catalog = new PlanCatalog([
        'Invalid Code!' => [
            'name' => 'Broken',
            'prices' => ['monthly' => 'price_broken', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
        'starter' => [
            'name' => 'Starter',
            'prices' => ['monthly' => 'price_starter_monthly', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);

    expect($catalog->resolveByPriceId('price_broken'))->toBeNull()
        ->and($catalog->resolveByPriceId('price_starter_monthly'))->not->toBeNull();
});

test('a malformed configured price id is excluded from resolution', function () {
    $catalog = new PlanCatalog([
        'starter' => [
            'name' => 'Starter',
            'prices' => ['monthly' => 'not-a-stripe-price', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);

    $definition = $catalog->get(PlanCode::from('starter'));

    expect($definition->priceId('monthly'))->toBeNull()
        ->and($catalog->resolveByPriceId('not-a-stripe-price'))->toBeNull();
});

test('a lookup value matching a malformed price id never resolves to a plan', function () {
    $catalog = new PlanCatalog(planCatalogFixturePlans());

    expect($catalog->resolveByPriceId('price_'))->toBeNull()
        ->and($catalog->resolveByPriceId('price_ with space'))->toBeNull()
        ->and($catalog->resolveByPriceId('PRICE_STARTER_MONTHLY'))->toBeNull();
});

test('a plan missing a display name is excluded from the catalog', function () {
    $catalog = new PlanCatalog([
        'starter' => [
            'prices' => ['monthly' => 'price_starter_monthly', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);

    expect($catalog->resolveByPriceId('price_starter_monthly'))->toBeNull()
        ->and($catalog->get(PlanCode::from('starter')))->toBeNull();
});

test('requesting an undeclared limit fails rather than inferring unlimited', function () {
    $catalog = new PlanCatalog(planCatalogFixturePlans());

    $definition = $catalog->get(PlanCode::from('starter'));

    $definition->limit('seats_undeclared');
})->throws(OutOfBoundsException::class);

test('an empty plan configuration resolves nothing', function () {
    $catalog = new PlanCatalog([]);

    expect($catalog->all())->toBe([])
        ->and($catalog->resolveByPriceId('price_starter_monthly'))->toBeNull();
});

test('the plan catalog is built from the billing configuration contract by default', function () {
    Config::set('billing.plans', planCatalogFixturePlans());

    $catalog = new PlanCatalog;

    expect($catalog->resolveByPriceId('price_pro_yearly')?->name)->toBe('Pro');
});

test('the subscription type is a single stable configured value independent of any plan or price id', function () {
    expect(config('billing.subscription_type'))
        ->toBe(config('subscription.type'))
        ->toBeString()
        ->and(config('billing.subscription_type'))
        ->not->toContain('price_');
});

test('provider-neutral mappings resolve within their configured provider only', function () {
    $catalog = new PlanCatalog([
        'starter' => [
            'name' => 'Starter',
            'providers' => [
                'stripe' => ['monthly' => 'price_starter_monthly', 'yearly' => null],
                'paymongo' => ['monthly' => 'plan_starter_monthly', 'yearly' => 'plan_starter_yearly'],
            ],
            'features' => ['inventory.view'],
            'limits' => ['locations' => 1],
        ],
    ]);

    expect($catalog->externalPlanId(PlanCode::from('starter'), 'paymongo', 'yearly'))->toBe('plan_starter_yearly')
        ->and($catalog->resolveExternalPlan('stripe', 'price_starter_monthly')?->code)->toBe(PlanCode::from('starter'))
        ->and($catalog->resolveExternalPlan('paymongo', 'plan_starter_monthly')?->code)->toBe(PlanCode::from('starter'))
        ->and($catalog->resolveExternalPlan('stripe', 'plan_starter_monthly'))->toBeNull()
        ->and($catalog->resolveExternalPlan('paymongo', 'price_starter_monthly'))->toBeNull()
        ->and($catalog->resolveExternalPlanInterval('paymongo', 'plan_starter_yearly'))->toBe('yearly');
});

test('provider-neutral mappings fail closed for blank IDs, unknown providers, and duplicate provider IDs', function () {
    $catalog = new PlanCatalog([
        'starter' => [
            'name' => 'Starter',
            'providers' => ['paymongo' => ['monthly' => 'plan_shared', 'yearly' => '']],
            'features' => [], 'limits' => [],
        ],
        'pro' => [
            'name' => 'Pro',
            'providers' => ['paymongo' => ['monthly' => 'plan_shared', 'yearly' => null]],
            'features' => [], 'limits' => [],
        ],
    ]);

    expect($catalog->resolveExternalPlan('paymongo', 'plan_shared'))->toBeNull()
        ->and($catalog->resolveExternalPlan('paymongo', ''))->toBeNull()
        ->and($catalog->resolveExternalPlan('unknown', 'plan_shared'))->toBeNull()
        ->and($catalog->externalPlanId(PlanCode::from('starter'), 'paymongo', 'yearly'))->toBeNull();
});
