<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformUserController extends Controller
{
    /**
     * Render the bounded, searchable platform user directory.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'verification' => [
                'nullable',
                Rule::in(['verified', 'unverified']),
            ],
            'role' => [
                'nullable',
                Rule::enum(OrganizationRole::class),
            ],
            'sort' => [
                'nullable',
                Rule::in([
                    'name',
                    'email',
                    'created_at',
                    'memberships',
                ]),
            ],
            'direction' => [
                'nullable',
                Rule::in(['asc', 'desc']),
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in([15, 25, 50]),
            ],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $verification = isset($validated['verification'])
            ? (string) $validated['verification']
            : null;
        $role = isset($validated['role'])
            ? OrganizationRole::from((string) $validated['role'])
            : null;
        $sort = isset($validated['sort'])
            ? (string) $validated['sort']
            : null;
        $direction = ($validated['direction'] ?? 'asc') === 'desc'
            ? 'desc'
            : 'asc';
        $perPage = (int) ($validated['per_page'] ?? 25);

        $usersQuery = User::query()
            ->select([
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
            ])
            ->withCount('organizationMemberships');

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';

            $usersQuery->where(
                static function (
                    Builder $query,
                ) use ($searchPattern): void {
                    $query
                        ->whereLike('name', $searchPattern)
                        ->orWhereLike('email', $searchPattern);
                },
            );
        }

        if ($verification === 'verified') {
            $usersQuery->whereNotNull('email_verified_at');
        } elseif ($verification === 'unverified') {
            $usersQuery->whereNull('email_verified_at');
        }

        if ($role !== null) {
            $usersQuery->whereIn(
                'id',
                OrganizationMembership::query()
                    ->select('user_id')
                    ->where('role', $role->value),
            );
        }

        if ($sort === null) {
            $usersQuery
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        } else {
            $sortColumn = match ($sort) {
                'email' => 'email',
                'created_at' => 'created_at',
                'memberships' => 'organization_memberships_count',
                default => 'name',
            };

            $usersQuery->orderBy($sortColumn, $direction);

            if ($sortColumn !== 'name') {
                $usersQuery->orderBy('name');
            }

            $usersQuery->orderBy('id');
        }

        $users = $usersQuery
            ->paginate($perPage)
            ->withQueryString()
            ->through(
                static fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'emailVerifiedAt' => $user->email_verified_at
                        ?->toIso8601String(),
                    'membershipCount' => (int) $user->getAttribute(
                        'organization_memberships_count',
                    ),
                    'createdAt' => $user->created_at?->toIso8601String(),
                ],
            );

        return Inertia::render('admin/users/index', [
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'from' => $users->firstItem(),
                'last_page' => $users->lastPage(),
                'next_page_url' => $users->nextPageUrl(),
                'per_page' => $users->perPage(),
                'prev_page_url' => $users->previousPageUrl(),
                'to' => $users->lastItem(),
                'total' => $users->total(),
            ],
            'filters' => [
                'search' => $search,
                'verification' => $verification,
                'role' => $role?->value,
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
            'roleOptions' => array_map(
                fn (OrganizationRole $role): array => [
                    'value' => $role->value,
                    'label' => $this->roleLabel($role),
                ],
                OrganizationRole::cases(),
            ),
        ]);
    }

    /**
     * Render one platform-safe user identity with bounded membership context.
     */
    public function show(User $user): Response
    {
        $membershipCount = $user->organizationMemberships()->count();

        $memberships = $user->organizationMemberships()
            ->select([
                'id',
                'organization_id',
                'user_id',
                'role',
                'created_at',
            ])
            ->with('organization:id,name,active')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(
                fn (OrganizationMembership $membership): array => [
                    'id' => $membership->id,
                    'role' => $membership->role->value,
                    'roleLabel' => $this->roleLabel($membership->role),
                    'createdAt' => $membership->created_at
                        ?->toIso8601String(),
                    'organization' => [
                        'id' => $membership->organization->id,
                        'name' => $membership->organization->name,
                        'active' => $membership->organization->active,
                    ],
                ],
            )
            ->values()
            ->all();

        return Inertia::render('admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerifiedAt' => $user->email_verified_at
                    ?->toIso8601String(),
                'createdAt' => $user->created_at?->toIso8601String(),
                'membershipCount' => $membershipCount,
            ],
            'memberships' => $memberships,
            'membershipsTruncated' => $membershipCount > 50,
        ]);
    }

    /**
     * Convert the organization role enum into stable platform-facing copy.
     */
    private function roleLabel(OrganizationRole $role): string
    {
        return match ($role) {
            OrganizationRole::Owner => 'Owner',
            OrganizationRole::Manager => 'Manager',
            OrganizationRole::InventoryStaff => 'Inventory staff',
            OrganizationRole::KitchenStaff => 'Kitchen staff',
            OrganizationRole::Auditor => 'Auditor',
        };
    }
}
