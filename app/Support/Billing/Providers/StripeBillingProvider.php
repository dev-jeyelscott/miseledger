<?php

namespace App\Support\Billing\Providers;

use App\Enums\BillingProvider as BillingProviderEnum;
use App\Models\BillingSubscription;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Stripe\Exception\InvalidRequestException;

/**
 * Wrap the existing Cashier/Stripe integration behind the billing provider contract.
 */
final class StripeBillingProvider implements BillingProvider
{
    /** Return the provider identity represented by this adapter. */
    public function identity(): BillingProviderEnum
    {
        return BillingProviderEnum::Stripe;
    }

    /**
     * Start the existing Cashier Stripe Checkout subscription flow.
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
    ): BillingCheckoutOutcome {
        $checkout = $organization
            ->newSubscription(
                (string) config('billing.subscription_type'),
                $externalPriceId,
            )
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => $metadata,
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
            ]);

        return BillingCheckoutOutcome::redirect(
            $checkout->redirect()->getTargetUrl(),
        );
    }

    /** Resolve the organization's existing Stripe billing portal URL. */
    public function billingPortalUrl(
        Organization $organization,
        string $returnUrl,
    ): string {
        return $organization->billingPortalUrl($returnUrl);
    }

    /** Retrieve and normalize authoritative Stripe subscription state. */
    public function retrieveSubscription(
        BillingSubscription $subscription,
    ): RemoteBillingSubscription {
        try {
            $remote = Cashier::stripe()
                ->subscriptions
                ->retrieve($subscription->external_subscription_id);
        } catch (InvalidRequestException $exception) {
            if ($exception->getHttpStatus() === 404) {
                throw new RemoteBillingSubscriptionNotFoundException;
            }

            throw $exception;
        }

        $externalCustomerId =
            $subscription->billingCustomer->external_customer_id;

        if ($remote->id !== $subscription->external_subscription_id
            || $remote->customer !== $externalCustomerId) {
            throw new \RuntimeException(
                'Stripe returned an invalid subscription response.',
            );
        }

        $item = $remote->items->data[0] ?? null;

        if ($item === null) {
            throw new \RuntimeException(
                'Stripe returned a subscription without a billing item.',
            );
        }

        $priceId = $item->price->id;
        $currentPeriodEndsAt = $this->timestamp(
            $item->current_period_end,
        );

        $endsAt = $remote->cancel_at_period_end === true
            ? $currentPeriodEndsAt
            : $this->timestamp($remote->cancel_at);

        return new RemoteBillingSubscription(
            externalSubscriptionId: $remote->id,
            externalCustomerId: $externalCustomerId,
            externalPlanId: $priceId,
            status: $remote->status,
            livemode: $remote->livemode,
            trialEndsAt: $this->timestamp($remote->trial_end),
            currentPeriodEndsAt: $currentPeriodEndsAt,
            nextBillingAt: $endsAt === null
                ? $currentPeriodEndsAt
                : null,
            endsAt: $endsAt,
            cancelledAt: $this->timestamp($remote->canceled_at),
        );
    }

    /** Preserve Stripe cancellation management through its billing portal. */
    public function cancelSubscription(
        BillingSubscription $subscription,
    ): void {
        throw new \RuntimeException(
            'Stripe subscription cancellation is managed through the billing portal.',
        );
    }

    /** Convert a Stripe Unix timestamp to UTC. */
    private function timestamp(mixed $value): ?Carbon
    {
        return is_int($value)
            ? Carbon::createFromTimestampUTC($value)
            : null;
    }
}
