<?php

use App\Actions\Billing\CreateRenewalInvoice;
use App\Actions\Billing\CreateUpgradeInvoice;
use App\Actions\Billing\EnsureManualPayMongoSubscription;
use App\Actions\Billing\SynchronizeStripeBillingProjection;
use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use App\Models\BillingCustomer;
use App\Models\BillingPlanVersion;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('billing.currency', 'PHP');
    Config::set('billing.versioned_catalog_enabled', true);
    Config::set('billing.plans.starter.manual_amounts', ['monthly' => 49_900, 'yearly' => 499_000]);
    Config::set('billing.plans.growth.manual_amounts', ['monthly' => 99_900, 'yearly' => 999_000]);
    Config::set('billing.providers.paymongo.manual_qrph', true);
});

/** Publish an immutable version with one manual-monthly PayMongo price. */
function publishedPlanVersion(string $planCode, int $version, int $amountMinor, string $interval = 'monthly'): BillingPlanVersion
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
        'interval' => $interval,
        'currency' => 'PHP',
        'amount_minor' => $amountMinor,
    ]);

    return $planVersion;
}

test('a new manual PayMongo subscription pins the current published version', function (): void {
    $current = publishedPlanVersion('starter', 1, 49_900);
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $subscription = app(EnsureManualPayMongoSubscription::class)->handle(
        $organization,
        $user,
        PlanCode::from('starter'),
        'monthly',
    );

    expect($subscription->plan_version_id)->toBe($current->id);
});

test('an unpublished plan never gets pinned to a draft or superseded version', function (): void {
    BillingPlanVersion::factory()->create([
        'plan_code' => 'starter',
        'version' => 1,
    ]);

    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $subscription = app(EnsureManualPayMongoSubscription::class)->handle(
        $organization,
        $user,
        PlanCode::from('starter'),
        'monthly',
    );

    expect($subscription->plan_version_id)->toBeNull();
});

test('the access resolver threads the subscription pin into commercial access', function (): void {
    $current = publishedPlanVersion('starter', 1, 49_900);
    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);

    BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'plan_version_id' => $current->id,
    ]);

    $access = OrganizationSubscriptionAccessResolver::resolve($organization->fresh());

    expect($access->planVersionId)->toBe($current->id);
});

test('a manual renewal invoice prices from the subscription pinned version, not the currently published one', function (): void {
    $v1 = publishedPlanVersion('starter', 1, 49_900);
    $v1->update(['superseded_at' => now()]);
    publishedPlanVersion('starter', 2, 79_900);

    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);

    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'plan_version_id' => $v1->id,
        'current_period_ends_at' => Carbon::parse('2026-09-26 00:00:00', 'UTC'),
    ]);

    $invoice = app(CreateRenewalInvoice::class)->handle(
        $subscription,
        Carbon::parse('2026-09-20 00:00:00', 'UTC'),
    );

    expect($invoice->amount)->toBe(49_900)
        ->and($invoice->plan_version_id)->toBe($v1->id);
});

test('an upgrade invoice freezes source and target plan versions and prices from them', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:00:00', 'UTC'));

    $sourceVersion = publishedPlanVersion('starter', 1, 30_000);
    $targetVersion = publishedPlanVersion('growth', 1, 60_000);

    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);

    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'plan_version_id' => $sourceVersion->id,
        'current_period_ends_at' => Carbon::parse('2026-09-10 00:00:00', 'UTC'),
    ]);

    $invoice = app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('growth'));

    // starter=1000/day, growth=2000/day, diff=1000/day * 10 remaining days.
    expect($invoice->amount)->toBe(10_000)
        ->and($invoice->plan_version_id)->toBe($sourceVersion->id)
        ->and($invoice->target_plan_version_id)->toBe($targetVersion->id);

    // Publishing a newer target version after invoice creation must not
    // change the already-frozen invoice.
    publishedPlanVersion('growth', 2, 999_000);
    $targetVersion->refresh();
    expect($targetVersion->superseded_at)->not->toBeNull();
    $invoice->refresh();
    expect($invoice->target_plan_version_id)->toBe($targetVersion->id)
        ->and($invoice->amount)->toBe(10_000);

    Carbon::setTestNow();
});

test('upgrade proration rounds a fractional daily-rate difference using integer arithmetic, never floats', function (): void {
    // 100 minor units over 30 nominal days does not divide evenly.
    $sourceVersion = publishedPlanVersion('starter', 1, 1_000);
    publishedPlanVersion('growth', 1, 1_100);

    Carbon::setTestNow(Carbon::parse('2026-09-01 00:00:00', 'UTC'));

    $organization = Organization::factory()->create();
    $customer = BillingCustomer::factory()->for($organization)->create(['provider' => BillingProvider::PayMongo]);

    $subscription = BillingSubscription::factory()->for($customer, 'billingCustomer')->create([
        'organization_id' => $organization->getKey(),
        'provider' => BillingProvider::PayMongo,
        'type' => config('billing.subscription_type'),
        'external_subscription_id' => null,
        'plan_code' => 'starter',
        'interval' => 'monthly',
        'collection_method' => BillingCollectionMethod::Manual,
        'provider_status' => 'active',
        'plan_version_id' => $sourceVersion->id,
        'current_period_ends_at' => Carbon::parse('2026-09-08 00:00:00', 'UTC'),
    ]);

    $invoice = app(CreateUpgradeInvoice::class)->handle($subscription, PlanCode::from('growth'));

    // diff = 100 minor units over 30 days = 3.333.../day * 7 remaining days = 23.33.. -> rounds to 23.
    expect($invoice->amount)->toBe(23);

    Carbon::setTestNow();
});

test('a brand-new Stripe subscription pins the version referenced by validated checkout metadata', function (): void {
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'prices' => ['monthly' => 'price_pin_starter', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);

    $current = publishedPlanVersion('starter', 1, 49_900);

    $organization = Organization::factory()->create(['stripe_id' => 'cus_pin_'.str()->random(8)]);
    $subscription = $organization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_pin_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_pin_starter',
        'quantity' => 1,
    ]);

    app(SynchronizeStripeBillingProjection::class)->handle($organization, $subscription, [
        'metadata' => ['plan_version_id' => (string) $current->id],
    ]);

    $projection = BillingSubscription::query()
        ->where('provider', BillingProvider::Stripe)
        ->where('external_subscription_id', $subscription->stripe_id)
        ->sole();

    expect($projection->plan_version_id)->toBe($current->id);
});

test('a brand-new Stripe subscription falls back to the current published version when checkout metadata is missing or forged', function (): void {
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'prices' => ['monthly' => 'price_pin_forged', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);

    $current = publishedPlanVersion('starter', 1, 49_900);

    $organization = Organization::factory()->create(['stripe_id' => 'cus_pin_'.str()->random(8)]);
    $subscription = $organization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_pin_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_pin_forged',
        'quantity' => 1,
    ]);

    // Metadata references a version belonging to a different plan code.
    $foreign = publishedPlanVersion('growth', 1, 60_000);

    app(SynchronizeStripeBillingProjection::class)->handle($organization, $subscription, [
        'metadata' => ['plan_version_id' => (string) $foreign->id],
    ]);

    $projection = BillingSubscription::query()
        ->where('provider', BillingProvider::Stripe)
        ->where('external_subscription_id', $subscription->stripe_id)
        ->sole();

    expect($projection->plan_version_id)->toBe($current->id);
});

test('re-synchronizing an existing pinned Stripe subscription never moves its pin, even after a newer version publishes', function (): void {
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'prices' => ['monthly' => 'price_pin_lifecycle', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);

    $v1 = publishedPlanVersion('starter', 1, 49_900);

    $organization = Organization::factory()->create(['stripe_id' => 'cus_pin_'.str()->random(8)]);
    $subscription = $organization->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_pin_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_pin_lifecycle',
        'quantity' => 1,
    ]);

    $synchronize = app(SynchronizeStripeBillingProjection::class);

    $synchronize->handle($organization, $subscription, [
        'metadata' => ['plan_version_id' => (string) $v1->id],
    ]);

    // A newer version publishes and supersedes v1.
    publishedPlanVersion('starter', 2, 79_900);

    // A lifecycle-only webhook redelivery (no metadata carried) must
    // preserve the existing pin rather than repin to the new current
    // version or clear it.
    $synchronize->handle($organization, $subscription->fresh(), [
        'canceled_at' => null,
    ]);

    $projection = BillingSubscription::query()
        ->where('provider', BillingProvider::Stripe)
        ->where('external_subscription_id', $subscription->stripe_id)
        ->sole();

    expect($projection->plan_version_id)->toBe($v1->id);
});
