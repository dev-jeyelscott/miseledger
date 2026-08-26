<?php

use App\Enums\PlanCode;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\PlanEntitlementResolver;
use Illuminate\Support\Facades\Config;

function planEntitlementFixtureCatalog(): PlanCatalog
{
    return new PlanCatalog([
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'prices' => [
                'monthly' => 'price_starter_monthly',
                'yearly' => null,
            ],
            'features' => ['inventory.view'],
            'limits' => ['locations' => 1, 'seats' => null],
        ],
    ]);
}

test('a configured feature enabled on the plan is granted', function () {
    $catalog = planEntitlementFixtureCatalog();

    expect(PlanEntitlementResolver::hasFeature(PlanCode::from('starter'), 'inventory.view', $catalog))->toBeTrue();
});

test('a feature not granted to the plan is denied', function () {
    $catalog = planEntitlementFixtureCatalog();

    expect(PlanEntitlementResolver::hasFeature(PlanCode::from('starter'), 'reports.view', $catalog))->toBeFalse();
});

test('a finite limit resolves with its configured value', function () {
    $catalog = planEntitlementFixtureCatalog();

    $limit = PlanEntitlementResolver::limit(PlanCode::from('starter'), 'locations', $catalog);

    expect($limit->isFinite)->toBeTrue()
        ->and($limit->isUnlimited)->toBeFalse()
        ->and($limit->isUnavailable)->toBeFalse()
        ->and($limit->value)->toBe(1);
});

test('a limit explicitly configured as unlimited is distinguishable from a missing limit', function () {
    $catalog = planEntitlementFixtureCatalog();

    $limit = PlanEntitlementResolver::limit(PlanCode::from('starter'), 'seats', $catalog);

    expect($limit->isUnlimited)->toBeTrue()
        ->and($limit->isFinite)->toBeFalse()
        ->and($limit->isUnavailable)->toBeFalse()
        ->and($limit->value)->toBeNull();
});

test('a null plan fails closed and grants no feature', function () {
    $catalog = planEntitlementFixtureCatalog();

    expect(PlanEntitlementResolver::hasFeature(null, 'inventory.view', $catalog))->toBeFalse();
});

test('a null plan fails closed and reports an unavailable limit rather than unlimited', function () {
    $catalog = planEntitlementFixtureCatalog();

    $limit = PlanEntitlementResolver::limit(null, 'locations', $catalog);

    expect($limit->isUnavailable)->toBeTrue()
        ->and($limit->isUnlimited)->toBeFalse()
        ->and($limit->isFinite)->toBeFalse()
        ->and($limit->value)->toBeNull();
});

test('a plan absent from the catalog fails closed and grants no feature', function () {
    $catalog = planEntitlementFixtureCatalog();

    expect(PlanEntitlementResolver::hasFeature(PlanCode::from('enterprise'), 'inventory.view', $catalog))->toBeFalse();
});

test('a plan absent from the catalog fails closed on limit lookup', function () {
    $catalog = planEntitlementFixtureCatalog();

    $limit = PlanEntitlementResolver::limit(PlanCode::from('enterprise'), 'locations', $catalog);

    expect($limit->isUnavailable)->toBeTrue();
});

test('an undeclared limit key fails closed rather than inferring unlimited', function () {
    $catalog = planEntitlementFixtureCatalog();

    $limit = PlanEntitlementResolver::limit(PlanCode::from('starter'), 'undeclared_dimension', $catalog);

    expect($limit->isUnavailable)->toBeTrue()
        ->and($limit->isUnlimited)->toBeFalse();
});

test('the entitlement resolver defaults to the billing configuration catalog', function () {
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'prices' => ['monthly' => 'price_starter_monthly', 'yearly' => null],
            'features' => ['inventory.view'],
            'limits' => [],
        ],
    ]);

    expect(PlanEntitlementResolver::hasFeature(PlanCode::from('starter'), 'inventory.view'))->toBeTrue();
});
