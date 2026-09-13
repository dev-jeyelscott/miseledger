<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\BillingProvider;
use App\Enums\PlanCode;
use App\Models\BillingCustomer;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\BillingObservability;
use App\Support\Billing\PlanCatalog;
use App\Support\Billing\Providers\BillingCheckoutOutcome;
use App\Support\Billing\Providers\BillingProviderManager;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Starts an organization-scoped provider Checkout session for a subscription.
 * The provider plan ID is always resolved from `PlanCatalog`: this is the
 * only path through which a Checkout session may be created, so no caller
 * can pass a raw provider plan ID through this boundary.
 *
 * Repeated requests made while a provider checkout is pending are serialized
 * per organization with a cache lock and reuse the still-pending checkout
 * outcome instead of creating a parallel one. The durable provider-neutral
 * projection is checked inside that lock before starting a new checkout;
 * Cashier remains an explicit migration fallback for legacy Stripe rows.
 */
final class CreateOrganizationCheckoutSession
{
    private const PENDING_CHECKOUT_TTL_MINUTES = 30;

    /**
     * The organization checkout lock lease must safely outlive the entire
     * protected provider workflow (customer + subscription + payment-intent
     * calls for PayMongo, or a single Checkout Session call for Stripe),
     * otherwise the lease can expire mid-flight and let a second request
     * acquire the lock before the durable subscription or pending-checkout
     * cache entry is written, producing a duplicate provider checkout.
     * Stripe's SDK alone allows up to 110s (30s connect + 80s read) per
     * call, so the lease is set well above that worst case.
     */
    private const LOCK_LEASE_SECONDS = 180;

    private const LOCK_WAIT_SECONDS = 5;

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

        $organizationId = (string) $organization->getKey();
        $pendingCacheKey = self::pendingCheckoutCacheKey($organizationId, $type);
        $pendingFingerprint = self::pendingCheckoutFingerprint(
            $provider,
            $plan,
            $interval,
        );

        try {
            return Cache::lock('billing:checkout:lock:'.$organizationId, self::LOCK_LEASE_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                fn (): BillingCheckoutOutcome => $this->startCheckoutWithinLock(
                    $organization,
                    $actor,
                    $plan,
                    $interval,
                    $provider,
                    $externalPlanId,
                    $organizationId,
                    $pendingCacheKey,
                    $pendingFingerprint,
                    $type,
                ),
            );
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'organization' => __('A checkout is already being started for this organization. Please try again in a moment.'),
            ]);
        }
    }

    private function startCheckoutWithinLock(
        Organization $organization,
        User $actor,
        PlanCode $plan,
        string $interval,
        BillingProvider $provider,
        string $externalPlanId,
        string $organizationId,
        string $pendingCacheKey,
        string $pendingFingerprint,
        string $type,
    ): BillingCheckoutOutcome {
        $pendingOutcome = Cache::get($pendingCacheKey);

        if (is_array($pendingOutcome)) {
            if (($pendingOutcome['fingerprint'] ?? null) !== $pendingFingerprint) {
                throw ValidationException::withMessages([
                    'plan' => __('A checkout is already pending for different billing terms. Complete or cancel it before starting another checkout.'),
                ]);
            }

            $cachedOutcome = $pendingOutcome['outcome'] ?? null;

            if (! is_array($cachedOutcome)) {
                throw new \InvalidArgumentException(
                    'The cached billing checkout outcome is malformed.',
                );
            }

            return BillingCheckoutOutcome::fromCacheValue($cachedOutcome);
        }

        if ($this->hasActiveSubscription($organization, $type)) {
            throw ValidationException::withMessages([
                'organization' => __('This organization already has an active subscription.'),
            ]);
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

        Cache::put(
            $pendingCacheKey,
            [
                'fingerprint' => $pendingFingerprint,
                'outcome' => $outcome->toCacheValue(),
            ],
            now()->addMinutes(self::PENDING_CHECKOUT_TTL_MINUTES),
        );

        return $outcome;
    }

    private static function pendingCheckoutCacheKey(string $organizationId, string $type): string
    {
        return "billing:checkout:pending:{$organizationId}:{$type}";
    }

    private static function pendingCheckoutFingerprint(
        BillingProvider $provider,
        PlanCode $plan,
        string $interval,
    ): string {
        return implode(':', [$provider->value, $plan->value, $interval]);
    }

    private function hasActiveSubscription(Organization $organization, string $type): bool
    {
        $hasProjectedSubscription = BillingSubscription::query()
            ->where('organization_id', $organization->getKey())
            ->where('type', $type)
            ->whereNull('cancelled_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->limit(2)
            ->exists();

        return $hasProjectedSubscription || $organization->subscribed($type);
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
