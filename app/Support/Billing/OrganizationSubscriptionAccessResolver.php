<?php

namespace App\Support\Billing;

use App\Enums\OrganizationAccessMode;
use App\Models\Organization;
use Laravel\Cashier\Subscription;
use Stripe\Subscription as StripeSubscription;

/**
 * Single server-side authority deriving an organization's commercial
 * access from Cashier's locally synchronized subscription/grace-period
 * state and the organization's generic trial fields. Reads only,
 * mutates nothing, and never issues Stripe API calls: Cashier's synced
 * `stripe_status`/`trial_ends_at`/`ends_at` columns remain the sole
 * inputs, so this never competes with Cashier as billing state authority.
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

        $subscription = $organization->subscription((string) config('billing.subscription_type'));

        if ($subscription === null) {
            return self::resolveWithoutSubscription($organization);
        }

        return self::resolveWithSubscription($subscription, $planCatalog ?? new PlanCatalog);
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
     * generic trial window, and it has never started Stripe checkout. Such
     * an organization must stay writable rather than unexpectedly becoming
     * read-only the moment commercial enforcement code runs; it only moves
     * to trial/subscription-derived access once operations assigns an
     * explicit rollout classification.
     */
    private static function isUnclassifiedLegacyOrganization(Organization $organization): bool
    {
        return $organization->rollout_classification === null
            && $organization->trial_ends_at === null
            && $organization->stripe_id === null;
    }

    private static function resolveWithSubscription(Subscription $subscription, PlanCatalog $planCatalog): OrganizationSubscriptionAccess
    {
        $onGracePeriod = $subscription->onGracePeriod();
        $status = $subscription->stripe_status;
        $onTrial = $subscription->onTrial();

        $plan = $subscription->stripe_price !== null
            ? $planCatalog->resolveByPriceId($subscription->stripe_price)?->code
            : null;

        [$accessMode, $billingWarning] = self::resolveAccessModeAndWarning($subscription, $onGracePeriod, $status);

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
    private static function resolveAccessModeAndWarning(Subscription $subscription, bool $onGracePeriod, string $status): array
    {
        if ($status === StripeSubscription::STATUS_UNPAID) {
            return [OrganizationAccessMode::ReadOnly, false];
        }

        if ($onGracePeriod) {
            return [OrganizationAccessMode::Writable, true];
        }

        if ($subscription->ended()) {
            return [OrganizationAccessMode::ReadOnly, false];
        }

        return match ($status) {
            StripeSubscription::STATUS_TRIALING,
            StripeSubscription::STATUS_ACTIVE => [OrganizationAccessMode::Writable, false],
            StripeSubscription::STATUS_PAST_DUE => [OrganizationAccessMode::Writable, true],
            default => [OrganizationAccessMode::ReadOnly, false],
        };
    }
}
