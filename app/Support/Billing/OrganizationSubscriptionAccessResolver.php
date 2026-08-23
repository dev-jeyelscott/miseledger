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
        $subscription = $organization->subscription((string) config('billing.subscription_type'));

        if ($subscription === null) {
            return self::resolveWithoutSubscription($organization);
        }

        return self::resolveWithSubscription($subscription, $planCatalog ?? new PlanCatalog);
    }

    private static function resolveWithoutSubscription(Organization $organization): OrganizationSubscriptionAccess
    {
        $onGenericTrial = $organization->onGenericTrial();

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

    private static function resolveWithSubscription(Subscription $subscription, PlanCatalog $planCatalog): OrganizationSubscriptionAccess
    {
        $onGracePeriod = $subscription->onGracePeriod();
        $status = $subscription->stripe_status;
        $onTrial = $subscription->onTrial();

        $plan = $subscription->stripe_price !== null
            ? $planCatalog->resolveByPriceId($subscription->stripe_price)?->code
            : null;

        return new OrganizationSubscriptionAccess(
            accessMode: self::resolveAccessMode($subscription, $onGracePeriod, $status),
            subscriptionStatus: $status,
            plan: $plan,
            onTrial: $onTrial,
            onGracePeriod: $onGracePeriod,
            billingWarning: $status === StripeSubscription::STATUS_PAST_DUE,
            trialEndsAt: $onTrial ? $subscription->trial_ends_at : null,
            endsAt: $subscription->ends_at,
        );
    }

    private static function resolveAccessMode(Subscription $subscription, bool $onGracePeriod, string $status): OrganizationAccessMode
    {
        if ($onGracePeriod) {
            return OrganizationAccessMode::Writable;
        }

        if ($subscription->ended()) {
            return OrganizationAccessMode::ReadOnly;
        }

        return match ($status) {
            StripeSubscription::STATUS_TRIALING,
            StripeSubscription::STATUS_ACTIVE,
            StripeSubscription::STATUS_PAST_DUE => OrganizationAccessMode::Writable,
            default => OrganizationAccessMode::ReadOnly,
        };
    }
}
