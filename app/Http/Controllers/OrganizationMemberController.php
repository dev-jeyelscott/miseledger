<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Http\Requests\Organizations\StoreOrganizationMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationMemberController extends Controller
{
    /**
     * Show users belonging to an organization.
     */
    public function index(Organization $organization): Response
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
}
