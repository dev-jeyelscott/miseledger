<?php

namespace App\Support\Billing;

/**
 * Stable feature codes gated at route/action and navigation boundaries via
 * `EnforceFeatureEntitlement` (server) and `HandleInertiaRequests` (shared
 * presentation context). These are the only feature codes referenced
 * outside `config('billing.plans')`, so route definitions and the
 * frontend entitlement context never drift out of sync.
 */
final class FeatureCode
{
    public const string Purchasing = 'purchasing';

    public const string Recipes = 'recipes';

    public const string ReportsExport = 'reports.export';

    public const string MultiLocation = 'locations.multi';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::Purchasing,
            self::Recipes,
            self::ReportsExport,
            self::MultiLocation,
        ];
    }
}
