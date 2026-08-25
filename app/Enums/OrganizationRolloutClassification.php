<?php

namespace App\Enums;

/**
 * Explicit pre-enforcement rollout classification for an organization,
 * assigned by approved operational governance (never inferred from
 * `created_at`/`updated_at`, and never auto-assigned by a migration
 * backfill). See `docs/existing-organization-rollout-plan.md`.
 *
 * `DevelopmentTest`, `InternalFree`, and `Grandfathered` are permanent
 * commercial exemptions: `OrganizationSubscriptionAccessResolver` keeps
 * them writable regardless of trial/subscription state. `TrialEligible`
 * and `ImmediatelyBillable` opt an organization back into the normal
 * trial/subscription-derived access mode once operations has set the
 * matching `trial_ends_at` or Cashier subscription.
 */
enum OrganizationRolloutClassification: string
{
    case DevelopmentTest = 'development_test';
    case InternalFree = 'internal_free';
    case Grandfathered = 'grandfathered';
    case TrialEligible = 'trial_eligible';
    case ImmediatelyBillable = 'immediately_billable';

    public function isPermanentlyExempt(): bool
    {
        return match ($this) {
            self::DevelopmentTest, self::InternalFree, self::Grandfathered => true,
            self::TrialEligible, self::ImmediatelyBillable => false,
        };
    }
}
