<?php

namespace App\Enums;

enum BillingLifecycleEvent: string
{
    case TrialEnding = 'trial_ending';
    case PaymentFailed = 'payment_failed';
    case ScheduledCancellation = 'scheduled_cancellation';
    case SubscriptionEnded = 'subscription_ended';
    case Recovered = 'recovered';

    /**
     * Return the safe, customer-facing summary for this lifecycle event.
     */
    public function message(): string
    {
        return match ($this) {
            self::TrialEnding => 'Your trial will end soon. Review your billing settings to continue service.',
            self::PaymentFailed => 'A subscription payment needs attention. Review your billing settings.',
            self::ScheduledCancellation => 'Your subscription is scheduled to end. Review your billing settings if this was unexpected.',
            self::SubscriptionEnded => 'Your subscription has ended. Review your billing settings to restore service.',
            self::Recovered => 'Your subscription is active again.',
        };
    }
}
