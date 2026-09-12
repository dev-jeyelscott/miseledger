<?php

namespace App\Support\Billing;

use App\Enums\OrganizationAccessMode;
use App\Enums\PlanCode;
use Carbon\CarbonInterface;

/**
 * Fresh server-derived commercial access state for one organization.
 */
final readonly class OrganizationSubscriptionAccess
{
    public function __construct(
        public OrganizationAccessMode $accessMode,
        public ?string $subscriptionStatus,
        public ?PlanCode $plan,
        public bool $onTrial,
        public bool $onGracePeriod,
        public bool $billingWarning,
        public ?CarbonInterface $trialEndsAt = null,
        public ?CarbonInterface $endsAt = null,
        public ?int $planVersionId = null,
    ) {}

    /**
     * Determine whether normal business writes are blocked.
     */
    public function isReadOnly(): bool
    {
        return $this->accessMode
            === OrganizationAccessMode::ReadOnly;
    }

    /**
     * Determine whether normal business writes are permitted.
     */
    public function isWritable(): bool
    {
        return $this->accessMode
            === OrganizationAccessMode::Writable;
    }
}
