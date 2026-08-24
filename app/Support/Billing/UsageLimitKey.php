<?php

namespace App\Support\Billing;

/**
 * Stable `config('billing.plans.*.limits')` keys enforced by
 * `OrganizationUsageLimitEnforcer`. These are the only quantitative-limit
 * keys referenced outside plan configuration, so enforcement call sites
 * never drift out of sync with the catalog.
 */
final class UsageLimitKey
{
    public const string Seats = 'seats';

    public const string Locations = 'locations';

    public const string InventoryItems = 'inventory_items';
}
