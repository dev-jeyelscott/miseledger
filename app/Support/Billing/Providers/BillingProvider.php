<?php

namespace App\Support\Billing\Providers;

use App\Enums\BillingProvider as BillingProviderEnum;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;

/**
 * The smallest set of provider capabilities MiseLedger's checkout and
 * management flows actually invoke. Deliberately excludes cancellation and
 * live subscription retrieval as separate methods: cancellation is exposed
 * exclusively through the hosted billing portal (`billingPortalUrl()`), and
 * no application code performs a live provider-side subscription retrieval
 * today — `OrganizationCheckoutStatusController` reads Cashier's local
 * synchronized row, the same category of read `OrganizationSubscriptionAccessResolver`
 * itself performs. This is not a general-purpose payment SDK boundary.
 */
interface BillingProvider
{
    public function identity(): BillingProviderEnum;

    /**
     * Starts checkout for an already-resolved, provider-specific price id.
     * Callers resolve `PlanCode` -> external price id via `PlanCatalog`
     * before calling this; the adapter never sees `PlanCode` or raw plan
     * configuration.
     *
     * @param  array<string, string>  $metadata
     */
    public function startCheckout(
        Organization $organization,
        string $externalPriceId,
        string $successUrl,
        string $cancelUrl,
        array $metadata,
        User $actor,
    ): BillingCheckoutOutcome;

    public function billingPortalUrl(Organization $organization, string $returnUrl): string;

    /**
     * Stop future renewal for a subscription owned by this provider. Provider
     * adapters perform only provider mechanics; the local projection keeps
     * the normalized paid-access end time used by commercial access.
     */
    public function cancelSubscription(BillingSubscription $subscription): void;
}
