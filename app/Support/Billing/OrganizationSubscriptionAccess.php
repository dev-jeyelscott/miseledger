<?php

namespace App\Support\Billing;

use App\Enums\OrganizationAccessMode;
use App\Enums\PlanCode;

/**
 * The resolved commercial access state for an organization at the moment
 * of resolution. Not persisted anywhere: it is always derived fresh from
 * the organization's generic trial fields and its locally synchronized
 * Cashier subscription/grace-period state.
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
    ) {}

    public function isReadOnly(): bool
    {
        return $this->accessMode === OrganizationAccessMode::ReadOnly;
    }

    public function isWritable(): bool
    {
        return $this->accessMode === OrganizationAccessMode::Writable;
    }
}
