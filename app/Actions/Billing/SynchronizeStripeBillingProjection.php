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
 * webhook payload is available (e.g. a local-only bootstrap). Each
 * provider-only field is only written when its raw key is actually present
 * in `$subscriptionObject`, so an omitted payload leaves schema defaults on
 * first-time creation and leaves previously webhook-derived values
 * untouched on an existing row, rather than fabricating or erasing them.
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

        $customerAttributes = ['external_customer_id' => $organization->stripe_id];

        if (array_key_exists('livemode', $subscriptionObject)) {
            $customerAttributes['livemode'] = (bool) $subscriptionObject['livemode'];
        }

        $billingCustomer = BillingCustomer::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'provider' => BillingProvider::Stripe],
            $customerAttributes,
        );

        $priceId = $subscription->stripe_price;
        $plan = $priceId !== null ? $this->planCatalog->resolveByPriceId($priceId) : null;
        $interval = $priceId !== null ? $this->planCatalog->resolveIntervalByPriceId($priceId) : null;

        $subscriptionAttributes = [
            'organization_id' => $organization->getKey(),
            'billing_customer_id' => $billingCustomer->getKey(),
            'type' => $subscription->type,
            'external_plan_id' => $priceId,
            'plan_code' => $plan?->code->value,
            'interval' => $interval,
            'provider_status' => $subscription->stripe_status,
            'trial_ends_at' => $subscription->trial_ends_at,
            'ends_at' => $subscription->ends_at,
        ];

        if (array_key_exists('livemode', $subscriptionObject)) {
            $subscriptionAttributes['livemode'] = (bool) $subscriptionObject['livemode'];
        }

        if (array_key_exists('current_period_end', $subscriptionObject)) {
            $periodEndsAt = self::timestamp($subscriptionObject['current_period_end']);
            $subscriptionAttributes['current_period_ends_at'] = $periodEndsAt;
            $subscriptionAttributes['next_billing_at'] = ($subscriptionObject['cancel_at_period_end'] ?? false) === true
                ? null
                : $periodEndsAt;
        }

        if (array_key_exists('canceled_at', $subscriptionObject)) {
            $subscriptionAttributes['cancelled_at'] = self::timestamp($subscriptionObject['canceled_at']);
        }

        BillingSubscription::query()->updateOrCreate(
            ['provider' => BillingProvider::Stripe, 'external_subscription_id' => $subscription->stripe_id],
            $subscriptionAttributes,
        );
    }

    private static function timestamp(mixed $value): ?Carbon
    {
        return is_int($value) ? Carbon::createFromTimestamp($value) : null;
    }
}
