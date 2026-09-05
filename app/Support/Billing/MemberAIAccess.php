<?php

namespace App\Support\Billing;

/**
 * Server-authoritative AI capability result for one organization membership.
 */
final readonly class MemberAIAccess
{
    public function __construct(
        public bool $featureGranted,
        public bool $memberEnabled,
        public bool $commerciallyWritable,
        public bool $organizationActive,
    ) {}

    public function canUse(): bool
    {
        return $this->featureGranted
            && $this->memberEnabled
            && $this->commerciallyWritable
            && $this->organizationActive;
    }
}
