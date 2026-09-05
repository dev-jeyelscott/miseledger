<?php

namespace App\Support\Billing;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;

/**
 * Resolves whether a specific organization member may use the AI assistant.
 */
final class MemberAIAccessResolver
{
    public static function resolve(
        Organization $organization,
        ?OrganizationMembership $membership,
        ?PlanCatalog $catalog = null,
    ): MemberAIAccess {
        $access = OrganizationSubscriptionAccessResolver::resolve(
            $organization,
            $catalog,
        );

        $belongsToOrganization = $membership?->organization_id
            === $organization->getKey();

        $memberEnabled = $belongsToOrganization
            && ($membership->role === OrganizationRole::Owner
                || $membership->ai_enabled);

        return new MemberAIAccess(
            featureGranted: OrganizationFeatureEntitlement::isGrantedForAccess(
                $access,
                FeatureCode::Assistant,
                $catalog,
            ),
            memberEnabled: $memberEnabled,
            commerciallyWritable: $access->isWritable(),
            organizationActive: $organization->active,
        );
    }
}
