<?php

namespace App\Support\Billing;

use App\Models\BillingSubscription;

/**
 * Deterministic, provider-call-free recurring-price resolution for
 * subscriptions pinned to a durable commercial plan version. Exists to give
 * a future Phase 4 projected MRR/ARR calculation a local, testable pricing
 * input and a pricing-coverage signal. This class deliberately makes no
 * decision about annual normalization, past-due inclusion, manual-renewal
 * treatment, grace-period treatment, FX conversion, or test-mode KPI
 * inclusion -- those remain unresolved finance-metric policy.
 */
final class SubscriptionRecurringPriceResolver
{
    public function __construct(private readonly PlanCatalog $planCatalog) {}

    /**
     * Resolve one subscription's exact pinned recurring-price tuple, or null
     * when it cannot be determined locally: unpinned, missing interval, an
     * unresolved billing currency, or the pinned version has no matching
     * price row for the subscription's own provider/collection/interval.
     * Never infers a price from legacy configuration, a provider price ID,
     * or historical invoices.
     *
     * @return array{
     *     provider: string,
     *     collectionMethod: string,
     *     interval: string,
     *     currency: string,
     *     amountMinor: int
     * }|null
     */
    public function resolve(BillingSubscription $subscription): ?array
    {
        if ($subscription->plan_version_id === null || $subscription->interval === null) {
            return null;
        }

        $currency = PlanCatalog::billingCurrency();

        if ($currency === null) {
            return null;
        }

        $price = $this->planCatalog->versionPrice(
            $subscription->plan_version_id,
            $subscription->provider,
            $subscription->collection_method,
            $subscription->interval,
            $currency,
        );

        if ($price === null) {
            return null;
        }

        return [
            'provider' => $subscription->provider->value,
            'collectionMethod' => $subscription->collection_method->value,
            'interval' => $subscription->interval,
            'currency' => $price->currency,
            'amountMinor' => $price->amount_minor,
        ];
    }

    /**
     * Report deterministic recurring-price coverage across every
     * not-yet-cancelled subscription of the configured subscription type.
     * Incomplete coverage is always explicit: every uncovered subscription
     * ID is listed rather than silently excluded from the total.
     *
     * @return array{
     *     total: int,
     *     covered: int,
     *     uncovered: int,
     *     uncoveredSubscriptionIds: list<int>
     * }
     */
    public function coverage(): array
    {
        $type = (string) config('billing.subscription_type');

        $subscriptions = BillingSubscription::query()
            ->where('type', $type)
            ->whereNull('cancelled_at')
            ->get();

        $uncoveredIds = [];

        foreach ($subscriptions as $subscription) {
            if ($this->resolve($subscription) === null) {
                $uncoveredIds[] = $subscription->id;
            }
        }

        $total = $subscriptions->count();
        $uncovered = count($uncoveredIds);

        return [
            'total' => $total,
            'covered' => $total - $uncovered,
            'uncovered' => $uncovered,
            'uncoveredSubscriptionIds' => $uncoveredIds,
        ];
    }
}
