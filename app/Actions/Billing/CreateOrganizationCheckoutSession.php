<?php

namespace App\Actions\Billing;

use App\Enums\PlanCode;
use App\Models\Organization;
use App\Support\Billing\PlanCatalog;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Checkout;

/**
 * Starts an organization-scoped Stripe Checkout session for a subscription.
 * The Stripe Price ID is always resolved from `PlanCatalog`: this is the
 * only path through which a Checkout session may be created, so no caller
 * can pass a raw Stripe Price ID through this boundary.
 */
final class CreateOrganizationCheckoutSession
{
    public function __construct(
        private readonly PlanCatalog $planCatalog,
    ) {}

    public function handle(Organization $organization, PlanCode $plan, string $interval): Checkout
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

        return $organization->newSubscription($type, $priceId)->checkout([
            'success_url' => route('organizations.settings.edit', $organization).'?checkout=success',
            'cancel_url' => route('organizations.settings.edit', $organization).'?checkout=cancelled',
            'metadata' => [
                'organization_id' => $organizationId,
            ],
            'subscription_data' => [
                'metadata' => [
                    'organization_id' => $organizationId,
                ],
            ],
        ]);
    }
}
