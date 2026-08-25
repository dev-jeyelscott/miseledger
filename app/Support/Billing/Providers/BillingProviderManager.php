<?php

namespace App\Support\Billing\Providers;

use App\Enums\BillingProvider as BillingProviderEnum;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Support\Billing\BillingIdentity;
use RuntimeException;

/**
 * Single boundary resolving which `BillingProvider` adapter services a
 * given operation. Never reads `.env` directly (only `defaultProvider()`
 * reads `config('billing.provider')`, which is itself environment-backed
 * configuration, not raw `.env` access), never reinterprets an existing
 * subscription's provider ownership using the current acquisition
 * provider, and never silently falls back between providers. Fails closed
 * when ownership is missing, unsupported, contradictory, or ambiguous.
 */
final class BillingProviderManager
{
    public function __construct(
        private readonly StripeBillingProvider $stripe,
        private readonly PayMongoBillingProvider $payMongo,
    ) {}

    /**
     * The provider new subscription acquisition should use, for an
     * organization with no existing provider-owned subscription.
     */
    public function defaultProvider(): BillingProviderEnum
    {
        $configured = config('billing.provider');
        $provider = is_string($configured) ? BillingIdentity::provider($configured) : null;

        if ($provider === null) {
            throw new RuntimeException(
                'Production billing configuration requires BILLING_PROVIDER to be explicitly set to stripe or paymongo.',
            );
        }

        return $provider;
    }

    /**
     * Resolves the implemented adapter for a specific provider. Reports an
     * enum-valid but not-yet-implemented provider as unavailable rather
     * than routing the request to a different provider.
     */
    public function provider(BillingProviderEnum $provider): BillingProvider
    {
        return match ($provider) {
            BillingProviderEnum::Stripe => $this->stripe,
            BillingProviderEnum::PayMongo => $this->payMongo,
        };
    }

    /**
     * Resolves the provider that owns an organization's existing billing
     * relationship from the durable `billing_subscriptions` projection —
     * never from the currently configured acquisition provider. Falls back
     * to `defaultProvider()` only when no provider-owned subscription
     * exists yet (e.g. servicing a not-yet-subscribed organization's
     * billing-portal access), which is inert while Stripe is the only
     * implemented adapter.
     */
    public function providerForOrganization(Organization $organization): BillingProvider
    {
        $providers = BillingSubscription::query()
            ->where('organization_id', $organization->getKey())
            ->distinct()
            ->pluck('provider');

        if ($providers->isEmpty()) {
            return $this->provider($this->defaultProvider());
        }

        if ($providers->count() > 1) {
            throw new RuntimeException(
                "Organization [{$organization->getKey()}] has ambiguous billing provider ownership across multiple providers.",
            );
        }

        $provider = $providers->sole();

        return $this->provider($provider instanceof BillingProviderEnum ? $provider : BillingProviderEnum::from($provider));
    }
}
