<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Allow access only to active organizations the user belongs to.
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
}
