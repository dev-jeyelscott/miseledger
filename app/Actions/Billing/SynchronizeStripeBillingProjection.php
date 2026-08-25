<?php

namespace App\Actions\Billing;

use App\Enums\BillingProvider;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\PlanCatalog;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription;

/**
 * Non-destructively synchronizes the durable, provider-neutral
 * `billing_customers`/`billing_subscriptions` projection from Cashier's
 * already-authoritative, locally synchronized Stripe subscription state.
 * A pure projection sync (`updateOrCreate`), not a lifecycle side-effect:
 * unlike audit entries/notifications, re-running it for a redelivered
 * webhook (or a bootstrap backfill) is naturally idempotent and needs no
 * separate dedup guard.
 *
 * `$subscription` is Cashier's own freshly-committed local row and is the
 * source of truth for every field it stores (status, price, trial/ends
 * dates) — reused rather than re-derived from the raw payload so this
 * projection can never diverge from Cashier's own computed semantics (e.g.
 * trial-aware `ends_at` on a scheduled cancellation). `$subscriptionObject`
 * is the raw webhook payload, the only source for fields Cashier's
 * `subscriptions` table does not persist at all (`current_period_end`,
 * `cancel_at`, `canceled_at`, `livemode`); pass an empty array when no
 * webhook payload is available (e.g. a local-only bootstrap), and those
 * fields are left null/false rather than fabricated or fetched via a
 * Stripe API call.
 */
final class SynchronizeStripeBillingProjection
{
    public function __construct(private readonly PlanCatalog $planCatalog) {}

    /**
     * @param  array<string, mixed>  $subscriptionObject  Raw Stripe subscription object, if available.
     */
    public function handle(Organization $organization, Subscription $subscription, array $subscriptionObject = []): void
    {
        if ($organization->stripe_id === null) {
            return;
        }

        $livemode = (bool) ($subscriptionObject['livemode'] ?? false);

        $billingCustomer = BillingCustomer::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'provider' => BillingProvider::Stripe],
            ['external_customer_id' => $organization->stripe_id, 'livemode' => $livemode],
        );

        $priceId = $subscription->stripe_price;
        $plan = $priceId !== null ? $this->planCatalog->resolveByPriceId($priceId) : null;
        $interval = $priceId !== null ? $this->planCatalog->resolveIntervalByPriceId($priceId) : null;

        BillingSubscription::query()->updateOrCreate(
            ['provider' => BillingProvider::Stripe, 'external_subscription_id' => $subscription->stripe_id],
            [
                'organization_id' => $organization->getKey(),
                'billing_customer_id' => $billingCustomer->getKey(),
                'type' => $subscription->type,
                'external_plan_id' => $priceId,
                'plan_code' => $plan?->code->value,
                'interval' => $interval,
                'provider_status' => $subscription->stripe_status,
                'livemode' => $livemode,
                'trial_ends_at' => $subscription->trial_ends_at,
                'current_period_ends_at' => self::timestamp($subscriptionObject['current_period_end'] ?? null),
                'next_billing_at' => ($subscriptionObject['cancel_at_period_end'] ?? false) === true
                    ? null
                    : self::timestamp($subscriptionObject['current_period_end'] ?? null),
                'ends_at' => $subscription->ends_at,
                'cancelled_at' => self::timestamp($subscriptionObject['canceled_at'] ?? null),
            ],
        );
    }

    private static function timestamp(mixed $value): ?Carbon
    {
        return is_int($value) ? Carbon::createFromTimestamp($value) : null;
    }
}
