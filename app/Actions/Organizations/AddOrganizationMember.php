<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Billing\OrganizationUsageLimitEnforcer;
use App\Support\Billing\UsageLimitKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AddOrganizationMember
{
    public function __construct(
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /**
     * Add an existing user to an organization with an explicit role.
     */
    public function handle(
        Organization $organization,
        User $actor,
        User $user,
        OrganizationRole $role,
    ): OrganizationMembership {
        return DB::transaction(function () use (
            $organization,
            $actor,
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

            OrganizationUsageLimitEnforcer::assertCanAdd(
                lockedOrganization: $lockedOrganization,
                limitKey: UsageLimitKey::Seats,
                currentUsage: $lockedOrganization->memberships()->count(),
                errorField: 'email',
                errorMessage: __('This organization has reached its member limit for the current plan.'),
            );

            $membership = $lockedOrganization->memberships()->create([
                'user_id' => $user->getKey(),
                'role' => $role,
            ]);

            $this->recordAuditEntry->handle(
                organization: $lockedOrganization,
                actor: $actor,
                action: 'organization_membership.role_assigned',
                entityType: 'organization_membership',
                entityId: $membership->id,
                beforeData: null,
                afterData: [
                    'user_id' => $user->getKey(),
                    'role' => $role->value,
                ],
                correlationId: "organization_membership:{$membership->id}:assign",
            );

            return $membership;
        });
    }
}
