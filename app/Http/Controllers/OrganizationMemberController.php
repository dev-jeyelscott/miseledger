<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\AddOrganizationMember;
use App\Actions\Organizations\ToggleOrganizationMemberAIAccess;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Http\Requests\Organizations\StoreOrganizationMemberRequest;
use App\Http\Requests\Organizations\UpdateOrganizationMemberAIAccessRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationMemberController extends Controller
{
    /**
     * Show users belonging to an organization.
     */
    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize(
            OrganizationPermission::UsersManage->value,
            $organization,
        );

        $members = $organization->memberships()
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(
                static fn (OrganizationMembership $membership): array => [
                    'id' => $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->value,
                    'ai_enabled' => $membership->role === OrganizationRole::Owner
                        || $membership->ai_enabled,
                ],
            )
            ->values()
            ->all();

        $roles = array_map(
            static fn (OrganizationRole $role): array => [
                'value' => $role->value,
                'label' => Str::headline($role->value),
            ],
            OrganizationRole::cases(),
        );

        return Inertia::render('organizations/members', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'members' => $members,
            'roles' => $roles,
            'canManageAiAccess' => $this->isOwner($request, $organization),
        ]);
    }

    /**
     * Add an existing registered user to the organization.
     */
    public function store(
        StoreOrganizationMemberRequest $request,
        Organization $organization,
        AddOrganizationMember $addOrganizationMember,
    ): RedirectResponse {
        $user = User::query()
            ->where('email', (string) $request->validated('email'))
            ->firstOrFail();

        $role = OrganizationRole::from(
            (string) $request->validated('role'),
        );

        $addOrganizationMember->handle(
            $organization,
            $request->user(),
            $user,
            $role,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization member added.'),
        ]);

        return to_route(
            'organizations.members.index',
            $organization,
        );
    }

    /**
     * Enable or disable AI access for a non-owner organization member.
     */
    public function updateAIAccess(
        UpdateOrganizationMemberAIAccessRequest $request,
        Organization $organization,
        OrganizationMembership $membership,
        ToggleOrganizationMemberAIAccess $toggleOrganizationMemberAIAccess,
    ): RedirectResponse {
        $toggleOrganizationMemberAIAccess->handle(
            organization: $organization,
            actor: $request->user(),
            membership: $membership,
            aiEnabled: (bool) $request->validated('ai_enabled'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization member AI access updated.'),
        ]);

        return to_route('organizations.members.index', $organization);
    }

    private function isOwner(Request $request, Organization $organization): bool
    {
        $user = $request->user();

        return $user instanceof User
            && $user->organizationMemberships()
                ->whereBelongsTo($organization)
                ->where('role', OrganizationRole::Owner->value)
                ->exists();
    }
}
