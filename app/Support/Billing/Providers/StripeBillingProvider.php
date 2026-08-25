<?php

namespace App\Support\Billing\Providers;

use App\Enums\BillingProvider as BillingProviderEnum;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;
use Stripe\Subscription as StripeSubscription;

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
        User $actor,
    ): BillingCheckoutOutcome {
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

        return BillingCheckoutOutcome::redirect($checkout->redirect()->getTargetUrl());
    }

    public function billingPortalUrl(Organization $organization, string $returnUrl): string
    {
        return $organization->billingPortalUrl($returnUrl);
    }

    public function retrieveSubscription(BillingSubscription $subscription): RemoteBillingSubscription
    {
        try {
            $remote = Cashier::stripe()->subscriptions->retrieve($subscription->external_subscription_id);
        } catch (InvalidRequestException $exception) {
            if ($exception->getHttpStatus() === 404) {
                throw new RemoteBillingSubscriptionNotFoundException;
            }

            throw $exception;
        }

        if (! $remote instanceof StripeSubscription || $remote->id !== $subscription->external_subscription_id
            || $remote->customer !== $subscription->billingCustomer->external_customer_id
            || ! is_string($remote->status)) {
            throw new \RuntimeException('Stripe returned an invalid subscription response.');
        }

        $priceId = $remote->items->data[0]->price->id ?? null;

        if (! is_string($priceId) && $priceId !== null) {
            throw new \RuntimeException('Stripe returned an invalid subscription price.');
        }

        $currentPeriodEndsAt = $this->timestamp($remote->current_period_end);
        $endsAt = $remote->cancel_at_period_end === true
            ? $currentPeriodEndsAt
            : $this->timestamp($remote->cancel_at);

        return new RemoteBillingSubscription(
            externalSubscriptionId: $remote->id,
            externalCustomerId: $remote->customer,
            externalPlanId: $priceId,
            status: $remote->status,
            livemode: $remote->livemode,
            trialEndsAt: $this->timestamp($remote->trial_end),
            currentPeriodEndsAt: $currentPeriodEndsAt,
            nextBillingAt: $endsAt === null ? $currentPeriodEndsAt : null,
            endsAt: $endsAt,
            cancelledAt: $this->timestamp($remote->canceled_at),
        );
    }

    public function cancelSubscription(BillingSubscription $subscription): void
    {
        throw new \RuntimeException('Stripe subscription cancellation is managed through the billing portal.');
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_int($value) ? Carbon::createFromTimestampUTC($value) : null;
    }
}
