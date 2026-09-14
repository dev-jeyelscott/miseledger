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
    /**
     * Generic rejection message shared across unresolvable, duplicate, and
     * seat-limited outcomes so the response cannot be used to infer whether
     * an email belongs to a registered account outside the organization.
     */
    public const string GENERIC_REJECTION_MESSAGE = 'This email address cannot be added to the organization.';

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
                    'email' => __(self::GENERIC_REJECTION_MESSAGE),
                ]);
            }

            OrganizationUsageLimitEnforcer::assertCanAdd(
                lockedOrganization: $lockedOrganization,
                limitKey: UsageLimitKey::Seats,
                currentUsage: $lockedOrganization->memberships()->count(),
                errorField: 'email',
                errorMessage: __(self::GENERIC_REJECTION_MESSAGE),
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
