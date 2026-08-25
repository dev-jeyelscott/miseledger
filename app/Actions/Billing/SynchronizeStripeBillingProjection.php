<?php

namespace App\Actions\Billing;

use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\PlanCatalog;
use Illuminate\Support\Carbon;

/**
 * Non-destructively synchronizes the durable, provider-neutral
 * `billing_customers`/`billing_subscriptions` projection from Cashier's
 * already-authoritative, locally synchronized Stripe subscription state.
 * A pure projection sync (`updateOrCreate`), not a lifecycle side-effect:
 * unlike audit entries/notifications, re-running it for a redelivered
 * webhook is naturally idempotent and needs no separate dedup guard.
 */
final class SynchronizeStripeBillingProjection
{
    public function __construct(private readonly PlanCatalog $planCatalog) {}

    /**
     * @param  array<string, mixed>  $subscriptionObject  Raw Stripe subscription object.
     */
    public function handle(Organization $organization, array $subscriptionObject): void
    {
        if ($organization->stripe_id === null) {
            return;
        }

        $subscriptionId = $subscriptionObject['id'] ?? null;

        if (! is_string($subscriptionId)) {
            return;
        }

        $livemode = (bool) ($subscriptionObject['livemode'] ?? false);

        $billingCustomer = BillingCustomer::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'provider' => BillingProvider::Stripe],
            ['external_customer_id' => $organization->stripe_id, 'livemode' => $livemode],
        );

        $priceId = data_get($subscriptionObject, 'items.data.0.price.id');
        $priceId = is_string($priceId) ? $priceId : null;

        $plan = $priceId !== null ? $this->planCatalog->resolveByPriceId($priceId) : null;
        $interval = $priceId !== null ? $this->planCatalog->resolveIntervalByPriceId($priceId) : null;

        BillingSubscription::query()->updateOrCreate(
            ['provider' => BillingProvider::Stripe, 'external_subscription_id' => $subscriptionId],
            [
                'organization_id' => $organization->getKey(),
                'billing_customer_id' => $billingCustomer->getKey(),
                'external_plan_id' => $priceId,
                'plan_code' => $plan?->code->value,
                'interval' => $interval,
                'provider_status' => $subscriptionObject['status'] ?? null,
                'livemode' => $livemode,
                'trial_ends_at' => self::timestamp($subscriptionObject['trial_end'] ?? null),
                'current_period_ends_at' => self::timestamp($subscriptionObject['current_period_end'] ?? null),
                'next_billing_at' => ($subscriptionObject['cancel_at_period_end'] ?? false) === true
                    ? null
                    : self::timestamp($subscriptionObject['current_period_end'] ?? null),
                'ends_at' => self::timestamp($subscriptionObject['cancel_at'] ?? null),
                'cancelled_at' => self::timestamp($subscriptionObject['canceled_at'] ?? null),
            ],
        );
    }

    private static function timestamp(mixed $value): ?Carbon
    {
        return is_int($value) ? Carbon::createFromTimestamp($value) : null;
    }
}
