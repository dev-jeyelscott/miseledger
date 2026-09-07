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

    /**
     * Return the first server-authoritative reason that prevents AI use.
     */
    public function unavailableReason(): ?string
    {
        if (! $this->organizationActive) {
            return 'organization_inactive';
        }

        if (! $this->commerciallyWritable) {
            return 'commercial_read_only';
        }

        if (! $this->featureGranted) {
            return 'feature_not_in_plan';
        }

        if (! $this->memberEnabled) {
            return 'member_access_disabled';
        }

        return null;
    }
}
