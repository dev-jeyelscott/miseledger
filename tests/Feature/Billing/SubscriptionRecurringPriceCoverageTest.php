<?php

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingPlanVersion;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\SubscriptionRecurringPriceResolver;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('billing.currency', 'PHP');
});

function coverageSubscription(?int $planVersionId): BillingSubscription
{
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);

    return BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'plan_version_id' => $planVersionId,
    ]);
}

test('a pinned subscription with a matching price resolves an exact integer amount', function (): void {
    $planVersion = BillingPlanVersion::factory()->create(['plan_code' => 'starter', 'version' => 1, 'published_at' => now()]);
    $planVersion->prices()->create([
        'provider' => BillingProvider::PayMongo,
        'collection_method' => BillingCollectionMethod::Manual,
        'interval' => 'monthly',
        'currency' => 'PHP',
        'amount_minor' => 49_900,
    ]);

    $subscription = coverageSubscription($planVersion->id);

    $resolved = app(SubscriptionRecurringPriceResolver::class)->resolve($subscription);

    expect($resolved)->toBe([
        'provider' => 'paymongo',
        'collectionMethod' => 'manual',
        'interval' => 'monthly',
        'currency' => 'PHP',
        'amountMinor' => 49_900,
    ]);
});

test('an unpinned subscription resolves no price', function (): void {
    $subscription = coverageSubscription(null);

    expect(app(SubscriptionRecurringPriceResolver::class)->resolve($subscription))->toBeNull();
});

test('coverage reports every uncovered subscription explicitly rather than omitting it', function (): void {
    $planVersion = BillingPlanVersion::factory()->create(['plan_code' => 'starter', 'version' => 1, 'published_at' => now()]);
    $planVersion->prices()->create([
        'provider' => BillingProvider::PayMongo,
        'collection_method' => BillingCollectionMethod::Manual,
        'interval' => 'monthly',
        'currency' => 'PHP',
        'amount_minor' => 49_900,
    ]);

    $covered = coverageSubscription($planVersion->id);
    $uncovered = coverageSubscription(null);

    $report = app(SubscriptionRecurringPriceResolver::class)->coverage();

    expect($report['total'])->toBe(2)
        ->and($report['covered'])->toBe(1)
        ->and($report['uncovered'])->toBe(1)
        ->and($report['uncoveredSubscriptionIds'])->toBe([$uncovered->id])
        ->and($report['uncoveredSubscriptionIds'])->not->toContain($covered->id);
});
