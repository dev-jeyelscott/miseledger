<?php

namespace App\Enums;

enum BillingLifecycleEvent: string
{
    case SubscriptionStarted = 'subscription_started';
    case PlanChanged = 'plan_changed';
    case TrialEnding = 'trial_ending';
    case PaymentFailed = 'payment_failed';
    case PaymentExpired = 'payment_expired';
    case ScheduledCancellation = 'scheduled_cancellation';
    case SubscriptionResumed = 'subscription_resumed';
    case SubscriptionEnded = 'subscription_ended';
    case Recovered = 'recovered';

    /**
     * Return the safe, customer-facing summary for this lifecycle event.
     */
    public function message(): string
    {
        return match ($this) {
            self::SubscriptionStarted => 'Your subscription is active.',
            self::PlanChanged => 'Your subscription plan has changed.',
            self::TrialEnding => 'Your trial will end soon. Review your billing settings to continue service.',
            self::PaymentFailed => 'A subscription payment needs attention. Review your billing settings.',
            self::PaymentExpired => 'A subscription payment request expired. Generate a new payment code to continue.',
            self::ScheduledCancellation => 'Your subscription is scheduled to end. Review your billing settings if this was unexpected.',
            self::SubscriptionResumed => 'Your scheduled cancellation has been removed.',
            self::SubscriptionEnded => 'Your subscription has ended. Review your billing settings to restore service.',
            self::Recovered => 'Your subscription is active again.',
        };
    }
}
