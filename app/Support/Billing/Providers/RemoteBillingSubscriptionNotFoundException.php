<?php

namespace App\Support\Billing\Providers;

use RuntimeException;

/** The provider confirmed that a locally projected subscription no longer exists. */
final class RemoteBillingSubscriptionNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The provider subscription could not be found.');
    }
}
