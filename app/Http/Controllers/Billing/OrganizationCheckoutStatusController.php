<?php

namespace App\Http\Controllers\Billing;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Billing\OrganizationSubscriptionAccess;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationCheckoutStatusController extends Controller
{
    /**
     * Show the post-Checkout success page. The Stripe redirect is never
     * treated as proof of activation: the locally synchronized subscription
     * state is re-read on every request, and the page renders a processing
     * state until Cashier's webhook has recorded the subscription.
     */
    public function success(Organization $organization): Response
    {
        Gate::authorize(
            OrganizationPermission::BillingManage->value,
            $organization,
        );

        $access = OrganizationSubscriptionAccessResolver::resolve($organization);

        return Inertia::render('organizations/billing/checkout-success', [
            'organization' => $this->organizationData($organization),
            'subscription' => $this->subscriptionData($access),
            'synchronized' => $organization->subscription(
                (string) config('billing.subscription_type'),
            ) !== null,
            'payment' => $this->paymentData(),
        ]);
    }

    /**
     * Show the Checkout cancellation page, returning the member safely to
     * the organization's billing context. No subscription state is read or
     * mutated here.
     */
    public function cancel(Organization $organization): Response
    {
        Gate::authorize(
            OrganizationPermission::BillingManage->value,
            $organization,
        );

        return Inertia::render('organizations/billing/checkout-cancelled', [
            'organization' => $this->organizationData($organization),
        ]);
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function organizationData(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }

    /**
     * Serialize only safe commercial state, matching the shared Inertia
     * subscription context shape. Never exposes Stripe secrets, customer
     * identifiers, payment-method tokens, or raw Cashier/Stripe objects.
     *
     * @return array{
     *     plan: string|null,
     *     status: string|null,
     *     accessMode: string,
     *     onTrial: bool,
     *     trialEndsAt: string|null,
     *     endsAt: string|null,
     *     billingWarning: bool
     * }
     */
    private function subscriptionData(OrganizationSubscriptionAccess $access): array
    {
        return [
            'plan' => $access->plan?->value,
            'status' => $access->subscriptionStatus,
            'accessMode' => $access->accessMode->value,
            'onTrial' => $access->onTrial,
            'trialEndsAt' => $access->trialEndsAt?->toISOString(),
            'endsAt' => $access->endsAt?->toISOString(),
            'billingWarning' => $access->billingWarning,
        ];
    }

    /** @return array{paymentIntentId: string, clientKey: string, publicKey: string, apiBaseUrl: string}|null */
    private function paymentData(): ?array
    {
        $payment = session('billing.checkout.payment');

        if (! is_array($payment) || ! array_all($payment, 'is_string')
            || ! isset($payment['payment_intent_id'], $payment['client_key'], $payment['public_key'], $payment['api_base_url'])) {
            return null;
        }

        return [
            'paymentIntentId' => $payment['payment_intent_id'],
            'clientKey' => $payment['client_key'],
            'publicKey' => $payment['public_key'],
            'apiBaseUrl' => $payment['api_base_url'],
        ];
    }
}
