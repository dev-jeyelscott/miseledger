<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Allow access only to active organizations the user belongs to.
     *
     * `active` is an administrative enable/disable flag only; it must not
     * be repurposed for subscription/billing state.
     */
    public function view(User $user, Organization $organization): bool
    {
        if (! $organization->active) {
            return false;
        }

        return $user->organizationMemberships()
            ->where('organization_id', $organization->getKey())
            ->exists();
    }

    /**
     * Restrict user administration to users.manage.
     */
    public function manageUsers(
        User $user,
        Organization $organization,
    ): bool {
        return $user->hasOrganizationPermission(
            $organization,
            OrganizationPermission::UsersManage,
        );
    }

    /**
     * Restrict organization configuration to organization.manage.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission(
            $organization,
            OrganizationPermission::OrganizationManage,
        );
    }
}
