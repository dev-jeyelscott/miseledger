<?php

namespace App\Support\Billing;

use App\Enums\BillingCollectionMethod;
use App\Enums\OrganizationAccessMode;
use App\Enums\PlanCode;
use App\Models\BillingSubscription;
use App\Models\Organization;

/**
 * Single server-side authority deriving an organization's commercial
 * access from the durable provider-neutral subscription projection and the
 * organization's generic trial fields. Reads only and never issues provider
 * API calls. Provider adapters synchronize the projection; this class owns
 * the provider-neutral commercial interpretation of that local state.
 *
 * Deliberately independent from `Organization.active`: administrative
 * deactivation is decided elsewhere and is never read or influenced here.
 */
final class OrganizationSubscriptionAccessResolver
{
    public static function resolve(Organization $organization, ?PlanCatalog $planCatalog = null): OrganizationSubscriptionAccess
    {
        if ($organization->rollout_classification?->isPermanentlyExempt() === true) {
            return self::resolveExempt();
        }

        $subscriptions = $organization->billingSubscriptions()
            ->where('type', (string) config('billing.subscription_type'))
            ->get();

        if ($subscriptions->isEmpty()) {
            return self::resolveWithoutSubscription($organization);
        }

        if ($subscriptions->count() !== 1) {
            return self::resolveDenied();
        }

        return self::resolveWithSubscription($subscriptions->sole(), $planCatalog ?? new PlanCatalog);
    }

    /**
     * Writable regardless of trial/subscription state: covers organizations
     * classified `development_test`, `internal_free`, or `grandfathered`
     * (see `docs/existing-organization-rollout-plan.md`).
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

    private static function resolveWithoutSubscription(Organization $organization): OrganizationSubscriptionAccess
    {
        $onGenericTrial = $organization->onGenericTrial();

        if (! $onGenericTrial && self::isUnclassifiedLegacyOrganization($organization)) {
            return self::resolveExempt();
        }

        return new OrganizationSubscriptionAccess(
            accessMode: $onGenericTrial ? OrganizationAccessMode::Writable : OrganizationAccessMode::ReadOnly,
            subscriptionStatus: null,
            plan: null,
            onTrial: $onGenericTrial,
            onGracePeriod: false,
            billingWarning: false,
            trialEndsAt: $onGenericTrial ? $organization->trial_ends_at : null,
            endsAt: null,
        );
    }

    /**
     * A pre-billing organization never touched by the trial/checkout flow:
     * no rollout classification has been assigned yet, it was never given a
     * generic trial window, and it has no durable provider customer. Such
     * an organization must stay writable rather than unexpectedly becoming
     * read-only the moment commercial enforcement code runs; it only moves
     * to trial/subscription-derived access once operations assigns an
     * explicit rollout classification.
     */
    private static function isUnclassifiedLegacyOrganization(Organization $organization): bool
    {
        return $organization->rollout_classification === null
            && $organization->trial_ends_at === null
            && ! $organization->billingCustomers()->exists();
    }

    private static function resolveWithSubscription(BillingSubscription $subscription, PlanCatalog $planCatalog): OrganizationSubscriptionAccess
    {
        try {
            $planCode = is_string($subscription->plan_code)
                ? PlanCode::from($subscription->plan_code)
                : null;
        } catch (\InvalidArgumentException) {
            $planCode = null;
        }
        $plan = $planCode !== null ? $planCatalog->get($planCode)?->code : null;
        $status = self::normalizedStatus($subscription);
        $onTrial = $status === 'trial';
        $onGracePeriod = self::isCancelledWithPaidAccess($subscription);
        [$accessMode, $billingWarning] = self::resolveAccessModeAndWarning(
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
            trialEndsAt: $onTrial ? $subscription->trial_ends_at : null,
            endsAt: $subscription->ends_at,
        );
    }

    /**
     * @return array{0: OrganizationAccessMode, 1: bool}
     */
    private static function resolveAccessModeAndWarning(BillingSubscription $subscription, bool $hasValidPlan, ?string $status, bool $onGracePeriod): array
    {
        if (! $hasValidPlan || self::hasEnded($subscription) || $status === 'unpaid') {
            return [OrganizationAccessMode::ReadOnly, false];
        }

        if ($onGracePeriod) {
            return [OrganizationAccessMode::Writable, true];
        }

        return match ($status) {
            'trial', 'active' => [OrganizationAccessMode::Writable, false],
            'past_due' => [OrganizationAccessMode::Writable, true],
            default => [OrganizationAccessMode::ReadOnly, false],
        };
    }

    private static function normalizedStatus(BillingSubscription $subscription): ?string
    {
        if (self::isCancelled($subscription)) {
            return 'cancelled';
        }

        if ($subscription->trial_ends_at?->isFuture() === true) {
            return 'trial';
        }

        return match ($subscription->provider_status) {
            'active', 'past_due', 'unpaid' => $subscription->provider_status,
            default => null,
        };
    }

    private static function isCancelledWithPaidAccess(BillingSubscription $subscription): bool
    {
        return self::isCancelled($subscription)
            && $subscription->ends_at?->isFuture() === true;
    }

    private static function isCancelled(BillingSubscription $subscription): bool
    {
        if ($subscription->collection_method === BillingCollectionMethod::Manual) {
            return $subscription->cancelled_at !== null
                || in_array($subscription->provider_status, ['cancelled', 'canceled'], true);
        }

        return $subscription->cancelled_at !== null
            || $subscription->ends_at !== null
            || in_array($subscription->provider_status, ['cancelled', 'canceled'], true);
    }

    private static function hasEnded(BillingSubscription $subscription): bool
    {
        return $subscription->ends_at !== null && ! $subscription->ends_at->isFuture();
    }

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
