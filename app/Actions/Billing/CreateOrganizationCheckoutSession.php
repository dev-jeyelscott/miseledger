<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use App\Models\BillingCustomer;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\BillingObservability;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\Providers\BillingCheckoutOutcome;
use App\Support\Billing\Providers\BillingProviderManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Starts an organization-scoped provider Checkout session for a subscription.
 * The provider plan ID is always resolved from `PlanCatalog`: this is the
 * only path through which a Checkout session may be created, so no caller
 * can pass a raw provider plan ID through this boundary.
 *
 * Creating a Stripe Checkout Session does not create a local Cashier
 * subscription record, so `$organization->subscribed()` stays false until
 * the webhook syncs the completed subscription. Repeated requests made
 * during that window are serialized per-organization with a cache lock and
 * reuse the still-pending Checkout Session URL instead of creating a
 * parallel one.
 */
final class CreateOrganizationCheckoutSession
{
    private const PENDING_CHECKOUT_TTL_MINUTES = 30;

    public function __construct(
        private readonly PlanCatalog $planCatalog,
        private readonly RecordAuditEntry $recordAuditEntry,
        private readonly BillingObservability $observability,
        private readonly BillingProviderManager $providerManager,
    ) {}

    public function handle(Organization $organization, User $actor, PlanCode $plan, string $interval): BillingCheckoutOutcome
    {
        $provider = $this->providerManager->defaultProvider();
        $externalPlanId = $this->planCatalog->externalPlanId($plan, $provider, $interval);

        if ($externalPlanId === null) {
            throw ValidationException::withMessages([
                'plan' => __('The selected plan is not available for the chosen billing interval.'),
            ]);
        }

        $type = (string) config('billing.subscription_type');

        if ($organization->subscribed($type)) {
            throw ValidationException::withMessages([
                'organization' => __('This organization already has an active subscription.'),
            ]);
        }

        $organizationId = (string) $organization->getKey();
        $pendingCacheKey = self::pendingCheckoutCacheKey($organizationId, $type);

        return Cache::lock('billing:checkout:lock:'.$organizationId, 10)->block(
            5,
            function () use ($organization, $actor, $plan, $interval, $provider, $externalPlanId, $organizationId, $pendingCacheKey): BillingCheckoutOutcome {
                $pendingOutcome = Cache::get($pendingCacheKey);

                if (is_array($pendingOutcome)) {
                    return BillingCheckoutOutcome::fromCacheValue($pendingOutcome);
                }

                try {
                    $billingProvider = $this->providerManager->provider($provider);

                    $outcome = $billingProvider->startCheckout(
                        $organization,
                        $externalPlanId,
                        route('organizations.billing.checkout.success', $organization),
                        route('organizations.billing.checkout.cancel', $organization),
                        ['organization_id' => $organizationId],
                        $actor,
                    );
                } catch (\Throwable $exception) {
                    $this->observability->checkoutFailure($organization, $provider, $exception);

                    throw $exception;
                }

                $this->persistStripeCustomerAfterCheckout($organization, $provider);

                $this->recordAuditEntry->handle(
                    $organization,
                    $actor,
                    'billing.checkout.started',
                    Organization::class,
                    $organization->getKey(),
                    null,
                    [
                        'plan' => $plan->value,
                        'interval' => $interval,
                    ],
                );

                Cache::put($pendingCacheKey, $outcome->toCacheValue(), now()->addMinutes(self::PENDING_CHECKOUT_TTL_MINUTES));

                return $outcome;
            },
        );
    }

    private static function pendingCheckoutCacheKey(string $organizationId, string $type): string
    {
        return "billing:checkout:pending:{$organizationId}:{$type}";
    }

    private function persistStripeCustomerAfterCheckout(Organization $organization, BillingProvider $provider): void
    {
        if ($provider !== BillingProvider::Stripe) {
            return;
        }

        $stripeId = $organization->fresh()->stripe_id;

        if ($stripeId === null) {
            return;
        }

        BillingCustomer::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'provider' => BillingProvider::Stripe],
            ['external_customer_id' => $stripeId, 'livemode' => false],
        );
    }
}
