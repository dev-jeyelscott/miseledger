<?php

namespace App\Support\Billing\Providers;

use Illuminate\Support\Carbon;

/** A safe, provider-neutral representation of authoritative subscription state. */
final readonly class RemoteBillingSubscription
{
    public function __construct(
        public string $externalSubscriptionId,
        public string $externalCustomerId,
        public ?string $externalPlanId,
        public string $status,
        public bool $livemode,
        public ?Carbon $trialEndsAt = null,
        public ?Carbon $currentPeriodEndsAt = null,
        public ?Carbon $nextBillingAt = null,
        public ?Carbon $endsAt = null,
        public ?Carbon $cancelledAt = null,
    ) {}

    /** @return array<string, Carbon|bool|string|null> */
    public function projection(): array
    {
        return [
            'external_plan_id' => $this->externalPlanId,
            'provider_status' => $this->status,
            'livemode' => $this->livemode,
            'trial_ends_at' => $this->trialEndsAt,
            'current_period_ends_at' => $this->currentPeriodEndsAt,
            'next_billing_at' => $this->nextBillingAt,
            'ends_at' => $this->endsAt,
            'cancelled_at' => $this->cancelledAt,
        ];
    }
}
