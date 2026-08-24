<?php

namespace App\Actions\Billing;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\PlanCode;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\BillingObservability;
use App\Support\Billing\PlanCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Starts an organization-scoped Stripe Checkout session for a subscription.
 * The Stripe Price ID is always resolved from `PlanCatalog`: this is the
 * only path through which a Checkout session may be created, so no caller
 * can pass a raw Stripe Price ID through this boundary.
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
    ) {}

    public function handle(Organization $organization, User $actor, PlanCode $plan, string $interval): string
    {
        $priceId = $this->planCatalog->get($plan)?->priceId($interval);

        if ($priceId === null) {
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
            function () use ($organization, $actor, $plan, $interval, $priceId, $organizationId, $type, $pendingCacheKey): string {
                $pendingUrl = Cache::get($pendingCacheKey);

                if (is_string($pendingUrl)) {
                    return $pendingUrl;
                }

                try {
                    $checkout = $organization->newSubscription($type, $priceId)->checkout([
                        'success_url' => route('organizations.billing.checkout.success', $organization),
                        'cancel_url' => route('organizations.billing.checkout.cancel', $organization),
                        'metadata' => [
                            'organization_id' => $organizationId,
                        ],
                        'subscription_data' => [
                            'metadata' => [
                                'organization_id' => $organizationId,
                            ],
                        ],
                    ]);
                } catch (\Throwable $exception) {
                    $this->observability->checkoutFailure($organization, $exception);

                    throw $exception;
                }

                $url = $checkout->redirect()->getTargetUrl();

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

                Cache::put($pendingCacheKey, $url, now()->addMinutes(self::PENDING_CHECKOUT_TTL_MINUTES));

                return $url;
            },
        );
    }

    private static function pendingCheckoutCacheKey(string $organizationId, string $type): string
    {
        return "billing:checkout:pending:{$organizationId}:{$type}";
    }
}
