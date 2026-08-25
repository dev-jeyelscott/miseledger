<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a billing lifecycle notification job finds an effect already claimed
 * by a prior, now-defunct attempt with no dispatch marker recorded. Whether that
 * prior attempt delivered the notification before terminating cannot be determined
 * locally, so redelivery is refused to avoid a duplicate externally visible send;
 * the failure is surfaced for manual reconciliation instead.
 */
final class AmbiguousBillingNotificationDeliveryException extends RuntimeException
{
    public function __construct(string $stripeEventId)
    {
        parent::__construct(
            "Billing lifecycle notification for Stripe event [{$stripeEventId}] was claimed by a prior attempt with no recorded delivery outcome; refusing to redeliver.",
        );
    }
}
