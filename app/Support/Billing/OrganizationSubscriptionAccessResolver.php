<?php

namespace App\Support\Billing;

use App\Enums\BillingCollectionMethod;
use App\Enums\BillingProvider;
use App\Enums\OrganizationAccessMode;
use App\Enums\PlanCode;
use App\Models\BillingSubscription;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Cashier\Subscription as CashierSubscription;

/**
 * Derives an organization's commercial access from synchronized local billing
 * state without issuing provider API calls or mutating business data.
 */
final class OrganizationSubscriptionAccessResolver
{
    /**
     * Resolve the organization's commercial access from local synchronized state.
     */
    public static function resolve(
        Organization $organization,
        ?PlanCatalog $planCatalog = null,
    ): OrganizationSubscriptionAccess {
        if ($organization->rollout_classification
            ?->isPermanentlyExempt() === true) {
            return self::resolveExempt();
        }

        $subscriptionType = (string) config('billing.subscription_type');

        $subscriptions = self::billingSubscriptions(
            $organization,
            $subscriptionType,
        );

        if ($subscriptions->isEmpty()) {
            $cashierSubscription = self::cashierSubscription(
                $organization,
                $subscriptionType,
            );

            if ($cashierSubscription instanceof CashierSubscription) {
                return self::resolveWithCashierSubscription(
                    $cashierSubscription,
                    $planCatalog ?? new PlanCatalog,
                );
            }

            return self::resolveWithoutSubscription($organization);
        }

        if ($subscriptions->count() !== 1) {
            return self::resolveDenied();
        }

        return self::resolveWithSubscription(
            $subscriptions->sole(),
            $planCatalog ?? new PlanCatalog,
        );
    }

    /**
     * Use a preloaded provider-neutral subscription collection when available.
     *
     * @return Collection<int, BillingSubscription>
     */
    private static function billingSubscriptions(
        Organization $organization,
        string $subscriptionType,
    ): Collection {
        if ($organization->relationLoaded('billingSubscriptions')) {
            return $organization->billingSubscriptions
                ->where('type', $subscriptionType)
                ->values();
        }

        return $organization->billingSubscriptions()
            ->where('type', $subscriptionType)
            ->get();
    }

    /**
     * Use preloaded Cashier subscriptions before falling back to its query API.
     */
    private static function cashierSubscription(
        Organization $organization,
        string $subscriptionType,
    ): ?CashierSubscription {
        if ($organization->relationLoaded('subscriptions')) {
            $subscription = $organization->subscriptions->first(
                static fn (
                    CashierSubscription $subscription,
                ): bool => $subscription->type === $subscriptionType,
            );

            return $subscription instanceof CashierSubscription
                ? $subscription
                : null;
        }

        $subscription = $organization->subscription($subscriptionType);

        return $subscription instanceof CashierSubscription
            ? $subscription
            : null;
    }

    /**
     * Resolve permanently exempt organizations as writable without billing state.
     */
    private static function resolveExempt(): OrganizationSubscriptionAccess
    {
        return new OrganizationSubscriptionAccess(
            accessMode: OrganizationAccessMode::Writable,
            subscriptionStatus: null,
            plan: null,
            onTrial: false,
            onGracePeriod: false,
            billingWarning: false,
            trialEndsAt: null,
            endsAt: null,
        );
    }

    /**
     * Resolve an organization that has no synchronized subscription.
     */
    private static function resolveWithoutSubscription(
        Organization $organization,
    ): OrganizationSubscriptionAccess {
        $onGenericTrial = $organization->onGenericTrial();

        if (! $onGenericTrial
            && self::isUnclassifiedLegacyOrganization($organization)) {
            return self::resolveExempt();
        }

        return new OrganizationSubscriptionAccess(
            accessMode: $onGenericTrial
                ? OrganizationAccessMode::Writable
                : OrganizationAccessMode::ReadOnly,
            subscriptionStatus: null,
            plan: null,
            onTrial: $onGenericTrial,
            onGracePeriod: false,
            billingWarning: false,
            trialEndsAt: $onGenericTrial
                ? $organization->trial_ends_at
                : null,
            endsAt: null,
        );
    }

    /**
     * Determine whether an unclassified organization predates billing ownership.
     */
    private static function isUnclassifiedLegacyOrganization(
        Organization $organization,
    ): bool {
        return $organization->rollout_classification === null
            && $organization->trial_ends_at === null
            && blank($organization->stripe_id)
            && ! self::hasBillingCustomers($organization);
    }

    /**
     * Check billing-customer presence without querying when already eager loaded.
     */
    private static function hasBillingCustomers(
        Organization $organization,
    ): bool {
        if ($organization->relationLoaded('billingCustomers')) {
            return $organization->billingCustomers->isNotEmpty();
        }

        return $organization->billingCustomers()->exists();
    }

    /**
     * Resolve provider-neutral subscription state into commercial access.
     */
    private static function resolveWithSubscription(
        BillingSubscription $subscription,
        PlanCatalog $planCatalog,
    ): OrganizationSubscriptionAccess {
        try {
            $planCode = is_string($subscription->plan_code)
                ? PlanCode::from($subscription->plan_code)
                : null;
        } catch (\InvalidArgumentException) {
            $planCode = null;
        }

        $plan = $planCode !== null
            ? $planCatalog->get($planCode)?->code
            : null;

        $status = self::normalizedStatus($subscription);
        $onTrial = $status === 'trial';
        $onGracePeriod = self::isCancelledWithPaidAccess($subscription);

        [$accessMode, $billingWarning] =
            self::resolveAccessModeAndWarning(
                $subscription,
                $plan !== null,
                $status,
                $onGracePeriod,
            );

        return new OrganizationSubscriptionAccess(
            accessMode: $accessMode,
            subscriptionStatus: $status,
            plan: $plan,
            onTrial: $onTrial,
            onGracePeriod: $onGracePeriod,
            billingWarning: $billingWarning,
            trialEndsAt: $onTrial
                ? $subscription->trial_ends_at
                : null,
            endsAt: $subscription->ends_at,
        );
    }

    /**
     * Resolve legacy Cashier rows while provider-neutral projections migrate.
     */
    private static function resolveWithCashierSubscription(
        CashierSubscription $subscription,
        PlanCatalog $planCatalog,
    ): OrganizationSubscriptionAccess {
        $plan = $planCatalog
            ->resolveExternalPlan(
                BillingProvider::Stripe,
                $subscription->stripe_price,
            )
            ?->code;

        $status = $subscription->stripe_status;

        $onTrial = $status === 'trialing'
            && $subscription->trial_ends_at?->isFuture() === true;

        $cancelled = $subscription->ends_at !== null
            || in_array(
                $status,
                ['cancelled', 'canceled'],
                true,
            );

        $onGracePeriod = $cancelled
            && $subscription->ends_at?->isFuture() === true;

        $ended = $subscription->ends_at !== null
            && ! $subscription->ends_at->isFuture();

        if ($plan === null || $ended || $status === 'unpaid') {
            $accessMode = OrganizationAccessMode::ReadOnly;
            $billingWarning = false;
        } elseif ($onGracePeriod) {
            $accessMode = OrganizationAccessMode::Writable;
            $billingWarning = true;
        } else {
            [$accessMode, $billingWarning] = match ($status) {
                'trialing',
                'active' => [
                    OrganizationAccessMode::Writable,
                    false,
                ],
                'past_due' => [
                    OrganizationAccessMode::Writable,
                    true,
                ],
                default => [
                    OrganizationAccessMode::ReadOnly,
                    false,
                ],
            };
        }

        return new OrganizationSubscriptionAccess(
            accessMode: $accessMode,
            subscriptionStatus: $status,
            plan: $plan,
            onTrial: $onTrial,
            onGracePeriod: $onGracePeriod,
            billingWarning: $billingWarning,
            trialEndsAt: $onTrial
                ? $subscription->trial_ends_at
                : null,
            endsAt: $subscription->ends_at,
        );
    }

    /**
     * Convert normalized subscription status into write access and warning state.
     *
     * @return array{0: OrganizationAccessMode, 1: bool}
     */
    private static function resolveAccessModeAndWarning(
        BillingSubscription $subscription,
        bool $hasValidPlan,
        ?string $status,
        bool $onGracePeriod,
    ): array {
        if (! $hasValidPlan
            || self::hasEnded($subscription)
            || $status === 'unpaid') {
            return [
                OrganizationAccessMode::ReadOnly,
                false,
            ];
        }

        if ($onGracePeriod) {
            return [
                OrganizationAccessMode::Writable,
                true,
            ];
        }

        return match ($status) {
            'trial',
            'active' => [
                OrganizationAccessMode::Writable,
                false,
            ],
            'past_due' => [
                OrganizationAccessMode::Writable,
                true,
            ],
            default => [
                OrganizationAccessMode::ReadOnly,
                false,
            ],
        };
    }

    /**
     * Normalize provider-neutral subscription fields into resolver vocabulary.
     */
    private static function normalizedStatus(
        BillingSubscription $subscription,
    ): ?string {
        if ($subscription->provider_status === 'unpaid') {
            return 'unpaid';
        }

        if (self::isCancelled($subscription)) {
            return 'cancelled';
        }

        if ($subscription->trial_ends_at?->isFuture() === true) {
            return 'trial';
        }

        return match ($subscription->provider_status) {
            'active',
            'past_due',
            'unpaid' => $subscription->provider_status,
            default => null,
        };
    }

    /**
     * Determine whether cancellation still retains already-paid access.
     */
    private static function isCancelledWithPaidAccess(
        BillingSubscription $subscription,
    ): bool {
        return self::isCancelled($subscription)
            && $subscription->ends_at?->isFuture() === true;
    }

    /**
     * Determine whether the normalized subscription is cancelled.
     */
    private static function isCancelled(
        BillingSubscription $subscription,
    ): bool {
        if ($subscription->collection_method
            === BillingCollectionMethod::Manual) {
            return $subscription->cancelled_at !== null
                || in_array(
                    $subscription->provider_status,
                    ['cancelled', 'canceled'],
                    true,
                );
        }

        return $subscription->cancelled_at !== null
            || $subscription->ends_at !== null
            || in_array(
                $subscription->provider_status,
                ['cancelled', 'canceled'],
                true,
            );
    }

    /**
     * Determine whether paid access has reached its synchronized end timestamp.
     */
    private static function hasEnded(
        BillingSubscription $subscription,
    ): bool {
        return $subscription->ends_at !== null
            && ! $subscription->ends_at->isFuture();
    }

    /**
     * Fail closed when synchronized subscription state is ambiguous.
     */
    private static function resolveDenied(): OrganizationSubscriptionAccess
    {
        return new OrganizationSubscriptionAccess(
            accessMode: OrganizationAccessMode::ReadOnly,
            subscriptionStatus: null,
            plan: null,
            onTrial: false,
            onGracePeriod: false,
            billingWarning: false,
            trialEndsAt: null,
            endsAt: null,
        );
    }
}
