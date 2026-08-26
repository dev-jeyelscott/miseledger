<?php

use App\Actions\Billing\SynchronizeStripeBillingProjection;
use App\Enums\BillingProvider;
use App\Models\BillingSubscription;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Laravel\Cashier\Subscription;

function projectionSyncPlans(): void
{
    Config::set('billing.plans', [
        'starter' => [
            'name' => 'Starter',
            'tier' => 1,
            'prices' => ['monthly' => 'price_projection_starter', 'yearly' => null],
            'features' => [],
            'limits' => [],
        ],
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function projectionSyncSubscription(Organization $organization, array $attributes = []): Subscription
{
    return $organization->subscriptions()->create(array_merge([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_'.str()->random(14),
        'stripe_status' => 'active',
        'stripe_price' => 'price_projection_starter',
        'quantity' => 1,
    ], $attributes));
}

test('synchronization maps status, plan, and dates for each lifecycle state', function (
    string $status,
    ?Carbon $trialEndsAt,
    ?Carbon $endsAt,
) {
    projectionSyncPlans();

    $organization = Organization::factory()->create(['stripe_id' => 'cus_projection_'.str()->random(8)]);
    $subscription = projectionSyncSubscription($organization, [
        'stripe_status' => $status,
        'trial_ends_at' => $trialEndsAt,
        'ends_at' => $endsAt,
    ]);

    app(SynchronizeStripeBillingProjection::class)->handle($organization, $subscription);

    $projection = BillingSubscription::query()
        ->where('provider', BillingProvider::Stripe)
        ->where('external_subscription_id', $subscription->stripe_id)
        ->sole();

    expect($projection->provider_status)->toBe($status)
        ->and($projection->plan_code)->toBe('starter')
        ->and($projection->interval)->toBe('monthly')
        ->and($projection->type)->toBe(config('billing.subscription_type'))
        ->and($projection->trial_ends_at?->timestamp)->toBe($trialEndsAt?->timestamp)
        ->and($projection->ends_at?->timestamp)->toBe($endsAt?->timestamp);
})->with([
    'trialing' => ['trialing', Carbon::now()->addDays(7), null],
    'active' => ['active', null, null],
    'past_due' => ['past_due', null, null],
    'unpaid' => ['unpaid', null, null],
    'scheduled cancellation' => ['active', null, Carbon::now()->addDays(7)],
    'ended' => ['canceled', null, Carbon::now()->subSecond()],
]);

test('repeated synchronization of the same subscription is idempotent', function () {
    projectionSyncPlans();

    $organization = Organization::factory()->create(['stripe_id' => 'cus_projection_repeat']);
    $subscription = projectionSyncSubscription($organization);

    $synchronize = app(SynchronizeStripeBillingProjection::class);

    $synchronize->handle($organization, $subscription);
    $synchronize->handle($organization, $subscription);
    $synchronize->handle($organization, $subscription->fresh());

    expect(BillingSubscription::query()->where('external_subscription_id', $subscription->stripe_id)->count())->toBe(1);
});

test('a scheduled cancellation payload clears next_billing_at while a normal renewal preserves it', function () {
    projectionSyncPlans();

    $organization = Organization::factory()->create(['stripe_id' => 'cus_projection_period']);
    $subscription = projectionSyncSubscription($organization);
    $periodEnd = Carbon::now()->addDays(30);

    app(SynchronizeStripeBillingProjection::class)->handle($organization, $subscription, [
        'current_period_end' => $periodEnd->timestamp,
        'cancel_at_period_end' => false,
    ]);

    $renewing = BillingSubscription::query()->where('external_subscription_id', $subscription->stripe_id)->sole();

    expect($renewing->current_period_ends_at?->timestamp)->toBe($periodEnd->timestamp)
        ->and($renewing->next_billing_at?->timestamp)->toBe($periodEnd->timestamp);

    app(SynchronizeStripeBillingProjection::class)->handle($organization, $subscription, [
        'current_period_end' => $periodEnd->timestamp,
        'cancel_at_period_end' => true,
    ]);

    expect(BillingSubscription::query()->where('external_subscription_id', $subscription->stripe_id)->sole()->next_billing_at)
        ->toBeNull();
});

test('billing:sync-projection backfills a pre-existing Cashier subscription with no prior projection row', function () {
    projectionSyncPlans();

    $organization = Organization::factory()->create(['stripe_id' => 'cus_projection_bootstrap']);
    $subscription = projectionSyncSubscription($organization, ['stripe_status' => 'active']);

    expect(BillingSubscription::query()->where('external_subscription_id', $subscription->stripe_id)->exists())->toBeFalse();

    $this->artisan('billing:sync-projection')->assertExitCode(0);

    $projection = BillingSubscription::query()->where('external_subscription_id', $subscription->stripe_id)->sole();

    expect($projection->provider_status)->toBe('active')
        ->and($projection->livemode)->toBeFalse()
        ->and($projection->current_period_ends_at)->toBeNull();

    $this->artisan('billing:sync-projection')->assertExitCode(0);

    expect(BillingSubscription::query()->where('external_subscription_id', $subscription->stripe_id)->count())->toBe(1);
});
