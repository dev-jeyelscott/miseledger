<?php

namespace App\Support\Billing;

/**
 * Stable quantitative limit identifiers enforced by MiseLedger.
 */
final class UsageLimitKey
{
    public const string Seats = 'seats';

    public const string Locations = 'locations';

    public const string InventoryItems = 'inventory_items';

    /**
     * Return every enforceable quantitative-limit identifier.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::Seats,
            self::Locations,
            self::InventoryItems,
        ];
    }
}
