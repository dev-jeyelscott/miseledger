<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AddOrganizationMember
{
    /**
     * Add an existing user to an organization with an explicit role.
     */
    public function handle(
        Organization $organization,
        User $user,
        OrganizationRole $role,
    ): OrganizationMembership {
        return DB::transaction(function () use (
            $organization,
            $user,
            $role,
        ): OrganizationMembership {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyExists = $lockedOrganization->memberships()
                ->where('user_id', $user->getKey())
                ->exists();

            if ($alreadyExists) {
                throw ValidationException::withMessages([
                    'email' => __('This user already belongs to the organization.'),
                ]);
            }

            return $lockedOrganization->memberships()->create([
                'user_id' => $user->getKey(),
                'role' => $role,
            ]);
        });
    }
}
