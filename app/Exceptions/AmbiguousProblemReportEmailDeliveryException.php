<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a problem report operator email job finds a report already claimed
 * by a prior, now-defunct attempt with no delivery marker recorded. Whether that
 * prior attempt delivered the email before terminating cannot be determined
 * locally, so redelivery is refused to avoid a duplicate externally visible send;
 * the failure is surfaced for manual reconciliation instead.
 */
final class AmbiguousProblemReportEmailDeliveryException extends RuntimeException
{
    public function __construct(int $reportId)
    {
        parent::__construct(
            "Operator email for problem report [{$reportId}] was claimed by a prior attempt with no recorded delivery outcome; refusing to redeliver.",
        );
    }
}
