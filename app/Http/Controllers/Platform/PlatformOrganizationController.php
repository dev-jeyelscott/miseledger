<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Support\Billing\OrganizationSubscriptionAccess;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use App\Support\Billing\PlanCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformOrganizationController extends Controller
{
    /**
     * Render the bounded, searchable platform organization directory.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
            'sort' => [
                'nullable',
                Rule::in([
                    'name',
                    'status',
                    'created_at',
                    'members',
                    'locations',
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
        $status = isset($validated['status'])
            ? (string) $validated['status']
            : null;
        $sort = isset($validated['sort'])
            ? (string) $validated['sort']
            : null;
        $direction = ($validated['direction'] ?? 'asc') === 'desc'
            ? 'desc'
            : 'asc';
        $perPage = (int) ($validated['per_page'] ?? 25);
        $subscriptionType = (string) config('billing.subscription_type');

        $organizationsQuery = Organization::query()
            ->select([
                'id',
                'name',
                'slug',
                'active',
                'trial_ends_at',
                'rollout_classification',
                'stripe_id',
                'created_at',
            ])
            ->withCount([
                'memberships',
                'locations',
            ])
            ->with([
                'billingSubscriptions' => static function (
                    Builder $query,
                ) use ($subscriptionType): void {
                    $query
                        ->select([
                            'id',
                            'organization_id',
                            'type',
                            'plan_code',
                            'collection_method',
                            'provider_status',
                            'trial_ends_at',
                            'ends_at',
                            'cancelled_at',
                        ])
                        ->where('type', $subscriptionType);
                },
                'billingCustomers' => static function (
                    Builder $query,
                ): void {
                    $query->select([
                        'id',
                        'organization_id',
                    ]);
                },
                'subscriptions' => static function (
                    Builder $query,
                ) use ($subscriptionType): void {
                    $query->where('type', $subscriptionType);
                },
            ]);

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';

            $organizationsQuery->where(
                static function (
                    Builder $query,
                ) use ($searchPattern): void {
                    $query
                        ->whereLike('name', $searchPattern)
                        ->orWhereLike('slug', $searchPattern);
                },
            );
        }

        if ($status === 'active') {
            $organizationsQuery->where('active', true);
        } elseif ($status === 'inactive') {
            $organizationsQuery->where('active', false);
        }

        if ($sort === null) {
            $organizationsQuery
                ->orderByDesc('active')
                ->orderBy('name')
                ->orderBy('id');
        } else {
            $sortColumn = match ($sort) {
                'status' => 'active',
                'created_at' => 'created_at',
                'members' => 'memberships_count',
                'locations' => 'locations_count',
                default => 'name',
            };

            $organizationsQuery->orderBy($sortColumn, $direction);

            if ($sortColumn !== 'name') {
                $organizationsQuery->orderBy('name');
            }

            $organizationsQuery->orderBy('id');
        }

        $planCatalog = new PlanCatalog;

        $organizations = $organizationsQuery
            ->paginate($perPage)
            ->withQueryString()
            ->through(
                function (
                    Organization $organization,
                ) use ($planCatalog): array {
                    $commercialAccess =
                        OrganizationSubscriptionAccessResolver::resolve(
                            $organization,
                            $planCatalog,
                        );

                    return [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'slug' => $organization->slug,
                        'active' => $organization->active,
                        'memberCount' => (int) $organization->getAttribute(
                            'memberships_count',
                        ),
                        'locationCount' => (int) $organization->getAttribute(
                            'locations_count',
                        ),
                        'commercialAccess' => $this->commercialAccessData(
                            $commercialAccess,
                        ),
                        'createdAt' => $organization->created_at
                            ?->toIso8601String(),
                    ];
                },
            );

        return Inertia::render('admin/organizations/index', [
            'organizations' => $organizations->items(),
            'pagination' => [
                'current_page' => $organizations->currentPage(),
                'from' => $organizations->firstItem(),
                'last_page' => $organizations->lastPage(),
                'next_page_url' => $organizations->nextPageUrl(),
                'per_page' => $organizations->perPage(),
                'prev_page_url' => $organizations->previousPageUrl(),
                'to' => $organizations->lastItem(),
                'total' => $organizations->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Render one organization with bounded member and location context.
     */
    public function show(Organization $organization): Response
    {
        $subscriptionType = (string) config('billing.subscription_type');

        $organization->load([
            'billingSubscriptions' => static function (
                Builder $query,
            ) use ($subscriptionType): void {
                $query
                    ->select([
                        'id',
                        'organization_id',
                        'type',
                        'plan_code',
                        'collection_method',
                        'provider_status',
                        'trial_ends_at',
                        'ends_at',
                        'cancelled_at',
                    ])
                    ->where('type', $subscriptionType);
            },
            'billingCustomers' => static function (
                Builder $query,
            ): void {
                $query->select([
                    'id',
                    'organization_id',
                ]);
            },
            'subscriptions' => static function (
                Builder $query,
            ) use ($subscriptionType): void {
                $query->where('type', $subscriptionType);
            },
        ]);

        $organization->loadCount([
            'memberships',
            'locations',
        ]);

        $memberCount = (int) $organization->getAttribute(
            'memberships_count',
        );
        $locationCount = (int) $organization->getAttribute(
            'locations_count',
        );

        $members = $organization->memberships()
            ->select([
                'id',
                'organization_id',
                'user_id',
                'role',
                'created_at',
            ])
            ->with('user:id,name,email,email_verified_at')
            ->orderBy('role')
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(
                fn (OrganizationMembership $membership): array => [
                    'id' => $membership->id,
                    'role' => $membership->role->value,
                    'roleLabel' => $this->roleLabel($membership->role),
                    'createdAt' => $membership->created_at
                        ?->toIso8601String(),
                    'user' => [
                        'id' => $membership->user->id,
                        'name' => $membership->user->name,
                        'email' => $membership->user->email,
                        'emailVerifiedAt' => $membership
                            ->user
                            ->email_verified_at
                            ?->toIso8601String(),
                    ],
                ],
            )
            ->values()
            ->all();

        $locations = $organization->locations()
            ->select([
                'id',
                'organization_id',
                'name',
                'code',
                'active',
                'created_at',
            ])
            ->orderByDesc('active')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(
                static fn (Location $location): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'code' => $location->code,
                    'active' => $location->active,
                    'createdAt' => $location->created_at
                        ?->toIso8601String(),
                ],
            )
            ->values()
            ->all();

        $commercialAccess =
            OrganizationSubscriptionAccessResolver::resolve(
                $organization,
                new PlanCatalog,
            );

        return Inertia::render('admin/organizations/show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'active' => $organization->active,
                'memberCount' => $memberCount,
                'locationCount' => $locationCount,
                'commercialAccess' => $this->commercialAccessData(
                    $commercialAccess,
                ),
                'createdAt' => $organization->created_at
                    ?->toIso8601String(),
            ],
            'members' => $members,
            'membersTruncated' => $memberCount > 50,
            'locations' => $locations,
            'locationsTruncated' => $locationCount > 50,
        ]);
    }

    /**
     * Convert resolved commercial access into minimal provider-neutral props.
     *
     * @return array{
     *     accessMode: string,
     *     subscriptionStatus: string|null,
     *     plan: string|null,
     *     onTrial: bool,
     *     onGracePeriod: bool,
     *     billingWarning: bool,
     *     trialEndsAt: string|null,
     *     endsAt: string|null
     * }
     */
    private function commercialAccessData(
        OrganizationSubscriptionAccess $access,
    ): array {
        return [
            'accessMode' => $access->accessMode->value,
            'subscriptionStatus' => $access->subscriptionStatus,
            'plan' => $access->plan?->value,
            'onTrial' => $access->onTrial,
            'onGracePeriod' => $access->onGracePeriod,
            'billingWarning' => $access->billingWarning,
            'trialEndsAt' => $access->trialEndsAt?->toIso8601String(),
            'endsAt' => $access->endsAt?->toIso8601String(),
        ];
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
