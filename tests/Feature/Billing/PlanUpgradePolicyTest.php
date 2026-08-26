<?php

use App\Enums\PlanCode;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\PlanUpgradePolicy;

function planUpgradePolicyCatalog(array $overrides = []): PlanCatalog
{
    return new PlanCatalog(array_replace([
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'providers' => ['paymongo' => ['monthly' => 'plan_starter_monthly', 'yearly' => null]],
            'features' => [],
            'limits' => [],
        ],
        'growth' => [
            'name' => 'Growth',
            'tier' => 2,
            'providers' => ['paymongo' => ['monthly' => 'plan_growth_monthly', 'yearly' => null]],
            'features' => [],
            'limits' => [],
        ],
        'business' => [
            'name' => 'Business',
            'tier' => 3,
            'providers' => ['paymongo' => ['monthly' => 'plan_business_monthly', 'yearly' => null]],
            'features' => [],
            'limits' => [],
        ],
    ], $overrides));
}

test('a higher-tier plan is a valid upgrade from a lower-tier plan', function (): void {
    $policy = new PlanUpgradePolicy(planUpgradePolicyCatalog());

    expect($policy->isEligibleUpgrade(PlanCode::from('starter'), PlanCode::from('growth')))->toBeTrue()
        ->and($policy->isEligibleUpgrade(PlanCode::from('starter'), PlanCode::from('business')))->toBeTrue()
        ->and($policy->isEligibleUpgrade(PlanCode::from('growth'), PlanCode::from('business')))->toBeTrue();
});

test('the same plan is never its own upgrade', function (): void {
    $policy = new PlanUpgradePolicy(planUpgradePolicyCatalog());

    expect($policy->isEligibleUpgrade(PlanCode::from('starter'), PlanCode::from('starter')))->toBeFalse()
        ->and($policy->isEligibleUpgrade(PlanCode::from('business'), PlanCode::from('business')))->toBeFalse();
});

test('a lower-tier plan is never a valid upgrade target', function (): void {
    $policy = new PlanUpgradePolicy(planUpgradePolicyCatalog());

    expect($policy->isEligibleUpgrade(PlanCode::from('growth'), PlanCode::from('starter')))->toBeFalse()
        ->and($policy->isEligibleUpgrade(PlanCode::from('business'), PlanCode::from('growth')))->toBeFalse()
        ->and($policy->isEligibleUpgrade(PlanCode::from('business'), PlanCode::from('starter')))->toBeFalse();
});

test('a plan missing an explicit tier is excluded from the catalog rather than defaulting to any rank', function (): void {
    $catalog = new PlanCatalog([
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'providers' => ['paymongo' => ['monthly' => 'plan_starter_monthly', 'yearly' => null]],
            'features' => [],
            'limits' => [],
        ],
        'growth' => [
            'name' => 'Growth',
            'providers' => ['paymongo' => ['monthly' => 'plan_growth_monthly', 'yearly' => null]],
            'features' => [],
            'limits' => [],
        ],
    ]);

    expect($catalog->get(PlanCode::from('growth')))->toBeNull();

    $policy = new PlanUpgradePolicy($catalog);

    expect($policy->isEligibleUpgrade(PlanCode::from('starter'), PlanCode::from('growth')))->toBeFalse();
});

test('a zero or negative tier is excluded from the catalog', function (): void {
    $catalog = planUpgradePolicyCatalog([
        'growth' => [
            'name' => 'Growth',
            'tier' => 0,
            'providers' => ['paymongo' => ['monthly' => 'plan_growth_monthly', 'yearly' => null]],
            'features' => [],
            'limits' => [],
        ],
    ]);

    expect($catalog->get(PlanCode::from('growth')))->toBeNull();
});

test('eligible upgrades from a plan are returned in ascending tier order', function (): void {
    $policy = new PlanUpgradePolicy(planUpgradePolicyCatalog());

    $eligible = $policy->eligibleUpgradesFrom(PlanCode::from('starter'));

    expect($eligible)->toHaveCount(2)
        ->and($eligible[0]->value)->toBe('growth')
        ->and($eligible[1]->value)->toBe('business')
        ->and($policy->eligibleUpgradesFrom(PlanCode::from('business')))->toBe([]);
});

test('the real production catalog assigns Starter, Growth, and Business strictly ascending tiers', function (): void {
    $policy = new PlanUpgradePolicy(app(PlanCatalog::class));

    expect($policy->isEligibleUpgrade(PlanCode::from('starter'), PlanCode::from('growth')))->toBeTrue()
        ->and($policy->isEligibleUpgrade(PlanCode::from('starter'), PlanCode::from('business')))->toBeTrue()
        ->and($policy->isEligibleUpgrade(PlanCode::from('growth'), PlanCode::from('business')))->toBeTrue()
        ->and($policy->isEligibleUpgrade(PlanCode::from('business'), PlanCode::from('growth')))->toBeFalse();
});
