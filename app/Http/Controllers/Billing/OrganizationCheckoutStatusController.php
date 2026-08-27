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
     * Show the post-Checkout success page from synchronized local billing state.
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
     * Show the Checkout cancellation page without changing subscription state.
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
    private function subscriptionData(
        OrganizationSubscriptionAccess $access,
    ): array {
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

    /**
     * @return array{
     *     paymentIntentId: string,
     *     clientKey: string,
     *     publicKey: string,
     *     apiBaseUrl: string
     * }|null
     */
    private function paymentData(): ?array
    {
        $payment = session('billing.checkout.payment');

        if (! is_array($payment)
            || ! array_all(
                $payment,
                static fn (mixed $value, mixed $_key): bool => is_string($value),
            )
            || ! isset(
                $payment['payment_intent_id'],
                $payment['client_key'],
                $payment['public_key'],
                $payment['api_base_url'],
            )) {
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
