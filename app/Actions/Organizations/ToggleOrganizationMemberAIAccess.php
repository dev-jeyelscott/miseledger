<?php

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ToggleOrganizationMemberAIAccess
{
    public function __construct(
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /**
     * Change explicit AI enablement for a non-owner organization member.
     * Owners always retain AI access through MemberAIAccessResolver.
     */
    public function handle(
        Organization $organization,
        User $actor,
        OrganizationMembership $membership,
        bool $aiEnabled,
    ): OrganizationMembership {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $membership,
            $aiEnabled,
        ): OrganizationMembership {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $actorMembership = $lockedOrganization->memberships()
                ->where('user_id', $actor->getKey())
                ->lockForUpdate()
                ->first();

            if ($actorMembership?->role !== OrganizationRole::Owner) {
                throw new AuthorizationException;
            }

            $lockedMembership = $lockedOrganization->memberships()
                ->whereKey($membership->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMembership->role === OrganizationRole::Owner && ! $aiEnabled) {
                throw ValidationException::withMessages([
                    'ai_enabled' => __('Owners always have AI access.'),
                ]);
            }

            if ($lockedMembership->ai_enabled === $aiEnabled) {
                return $lockedMembership;
            }

            $beforeData = ['ai_enabled' => $lockedMembership->ai_enabled];

            $lockedMembership->update(['ai_enabled' => $aiEnabled]);

            $this->recordAuditEntry->handle(
                organization: $lockedOrganization,
                actor: $actor,
                action: 'organization_membership.ai_access_updated',
                entityType: 'organization_membership',
                entityId: $lockedMembership->id,
                beforeData: $beforeData,
                afterData: ['ai_enabled' => $lockedMembership->ai_enabled],
            );

            return $lockedMembership;
        });
    }
}
