<?php

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Models\AuditLog;
use App\Models\BillingCustomer;
use App\Models\BillingPlanVersion;
use App\Models\BillingSubscription;
use App\Models\Organization;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('billing.currency', 'PHP');
});

function migrationSubscription(?int $planVersionId): BillingSubscription
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

function publishedVersionWithPrice(string $planCode, int $version, int $amountMinor): BillingPlanVersion
{
    BillingPlanVersion::query()
        ->where('plan_code', $planCode)
        ->whereNotNull('published_at')
        ->whereNull('superseded_at')
        ->update(['superseded_at' => now()]);

    $planVersion = BillingPlanVersion::factory()->create([
        'plan_code' => $planCode,
        'version' => $version,
        'published_at' => now(),
    ]);

    $planVersion->prices()->create([
        'provider' => BillingProvider::PayMongo,
        'collection_method' => BillingCollectionMethod::Manual,
        'interval' => 'monthly',
        'currency' => 'PHP',
        'amount_minor' => $amountMinor,
    ]);

    return $planVersion;
}

test('same-price migration pins the subscription and writes an audit entry', function (): void {
    $source = publishedVersionWithPrice('starter', 1, 49_900);
    $target = publishedVersionWithPrice('starter', 2, 49_900);
    $subscription = migrationSubscription($source->id);

    $this->artisan('billing:migrate-subscription-plan-version', [
        'organization' => $subscription->organization_id,
        'target_version' => $target->id,
        '--apply' => true,
    ])->assertSuccessful();

    expect($subscription->fresh()->plan_version_id)->toBe($target->id)
        ->and(AuditLog::query()->where('action', 'billing.subscription.plan_version_migrated')->count())->toBe(1);
});

test('a dry run never writes a change', function (): void {
    $source = publishedVersionWithPrice('starter', 1, 49_900);
    $target = publishedVersionWithPrice('starter', 2, 49_900);
    $subscription = migrationSubscription($source->id);

    $this->artisan('billing:migrate-subscription-plan-version', [
        'organization' => $subscription->organization_id,
        'target_version' => $target->id,
    ])->assertSuccessful();

    expect($subscription->fresh()->plan_version_id)->toBe($source->id);
});

test('repeating an applied migration is idempotent', function (): void {
    $source = publishedVersionWithPrice('starter', 1, 49_900);
    $target = publishedVersionWithPrice('starter', 2, 49_900);
    $subscription = migrationSubscription($source->id);

    $this->artisan('billing:migrate-subscription-plan-version', [
        'organization' => $subscription->organization_id,
        'target_version' => $target->id,
        '--apply' => true,
    ])->assertSuccessful();

    $this->artisan('billing:migrate-subscription-plan-version', [
        'organization' => $subscription->organization_id,
        'target_version' => $target->id,
        '--apply' => true,
    ])->assertSuccessful();

    expect($subscription->fresh()->plan_version_id)->toBe($target->id)
        ->and(AuditLog::query()->where('action', 'billing.subscription.plan_version_migrated')->count())->toBe(1);
});

test('a price-changing migration is refused and leaves the pin untouched', function (): void {
    $source = publishedVersionWithPrice('starter', 1, 49_900);
    $target = publishedVersionWithPrice('starter', 2, 79_900);
    $subscription = migrationSubscription($source->id);

    $this->artisan('billing:migrate-subscription-plan-version', [
        'organization' => $subscription->organization_id,
        'target_version' => $target->id,
        '--apply' => true,
    ])->assertFailed();

    expect($subscription->fresh()->plan_version_id)->toBe($source->id)
        ->and(AuditLog::query()->where('action', 'billing.subscription.plan_version_migrated')->count())->toBe(0);
});

test('a cross-plan-code target is refused', function (): void {
    $source = publishedVersionWithPrice('starter', 1, 49_900);
    $target = publishedVersionWithPrice('growth', 1, 49_900);
    $subscription = migrationSubscription($source->id);

    $this->artisan('billing:migrate-subscription-plan-version', [
        'organization' => $subscription->organization_id,
        'target_version' => $target->id,
        '--apply' => true,
    ])->assertFailed();

    expect($subscription->fresh()->plan_version_id)->toBe($source->id);
});

test('an unverifiable current price is refused rather than guessed', function (): void {
    $target = publishedVersionWithPrice('starter', 2, 49_900);
    $subscription = migrationSubscription(null);

    $this->artisan('billing:migrate-subscription-plan-version', [
        'organization' => $subscription->organization_id,
        'target_version' => $target->id,
        '--apply' => true,
    ])->assertFailed();

    expect($subscription->fresh()->plan_version_id)->toBeNull();
});
