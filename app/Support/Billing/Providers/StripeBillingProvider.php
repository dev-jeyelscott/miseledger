<?php

namespace App\Support\Billing\Providers;

use App\Enums\BillingProvider as BillingProviderEnum;
use App\Models\Organization;

/**
 * Wraps the current Cashier/Stripe integration behind `BillingProvider`.
 * Preserves the exact Cashier calls and Checkout/Portal option shapes the
 * application already relied on before this abstraction existed.
 */
final class StripeBillingProvider implements BillingProvider
{
    public function identity(): BillingProviderEnum
    {
        return BillingProviderEnum::Stripe;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    public function startCheckout(
        Organization $organization,
        string $externalPriceId,
        string $successUrl,
        string $cancelUrl,
        array $metadata,
    ): string {
        $checkout = $organization
            ->newSubscription((string) config('billing.subscription_type'), $externalPriceId)
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => $metadata,
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
            ]);

        return $checkout->redirect()->getTargetUrl();
    }

    public function billingPortalUrl(Organization $organization, string $returnUrl): string
    {
        return $organization->billingPortalUrl($returnUrl);
    }
}
