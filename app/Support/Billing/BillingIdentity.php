<?php

namespace App\Support\Billing;

use App\Enums\BillingProvider;

/**
 * Narrow boundary converting a raw provider string (configuration or an
 * external provider's own identifier) into `BillingProvider`, so business
 * logic never duplicates the raw `'stripe'`/`'paymongo'` string literals.
 */
final class BillingIdentity
{
    public static function provider(string $value): ?BillingProvider
    {
        return BillingProvider::tryFrom($value);
    }
}
