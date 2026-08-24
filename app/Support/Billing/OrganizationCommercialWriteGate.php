<?php

namespace App\Support\Billing;

use App\Enums\OrganizationPermission;
use App\Models\Organization;

/**
 * Single reusable commercial-write boundary derived from the P2
 * organization subscription access resolver. Intended to be composed with
 * `&&` at the tail of an existing ability check (`Gate::define` closures,
 * policy methods), so PHP's short-circuit evaluation guarantees the
 * existing tenant/membership/RBAC check always runs first: an unauthorized
 * request short-circuits before this is ever called, so it never learns
 * whether the target organization is commercially read-only.
 *
 * Safe (GET/HEAD/OPTIONS) requests and `billing.manage` (billing recovery)
 * are always permitted, so reads, reports, history, and billing recovery
 * keep working regardless of commercial state.
 */
final class OrganizationCommercialWriteGate
{
    public static function permits(
        Organization $organization,
        ?OrganizationPermission $permission = null,
    ): bool {
        if ($permission === OrganizationPermission::BillingManage) {
            return true;
        }

        if (request()->isMethodSafe()) {
            return true;
        }

        return ! OrganizationSubscriptionAccessResolver::resolve($organization)->isReadOnly();
    }
}
