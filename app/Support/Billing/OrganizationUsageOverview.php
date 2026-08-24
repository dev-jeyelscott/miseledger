<?php

namespace App\Support\Billing;

/**
 * A single quantitative dimension's current usage against the plan's
 * declared limit, for display only. Never used for enforcement:
 * `OrganizationUsageLimitEnforcer` remains the sole authority deciding
 * whether a new resource may be created.
 */
final readonly class OrganizationUsageOverview
{
    public function __construct(
        public string $key,
        public int $current,
        public ?int $limit,
        public bool $isUnlimited,
        public bool $atLimit,
    ) {}
}
