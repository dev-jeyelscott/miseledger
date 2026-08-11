<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'organizationContext' => $this->organizationContext($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Build the safe tenant context exposed to the frontend shell.
     *
     * @return array{
     *     active: array{id: int, name: string, slug: string}|null,
     *     memberships: list<array{
     *         organization: array{id: int, name: string, slug: string},
     *         role: string,
     *         permissions: list<string>
     *     }>
     * }
     */
    private function organizationContext(Request $request): array
    {
        $activeOrganization = $request->attributes->get(
            'activeOrganization',
        );

        $memberships = $request->attributes->get(
            'organizationMemberships',
        );

        $membershipData = [];

        if ($memberships instanceof Collection) {
            foreach ($memberships as $membership) {
                if (! $membership instanceof OrganizationMembership) {
                    continue;
                }

                $membershipData[] = [
                    'organization' => $this->organizationData(
                        $membership->organization,
                    ),
                    'role' => $membership->role->value,
                    'permissions' => array_map(
                        static fn (
                            OrganizationPermission $permission,
                        ): string => $permission->value,
                        $membership->role->permissions(),
                    ),
                ];
            }
        }

        return [
            'active' => $activeOrganization instanceof Organization
                ? $this->organizationData($activeOrganization)
                : null,
            'memberships' => $membershipData,
        ];
    }

    /**
     * Serialize only organization data required by the application shell.
     *
     * @return array{id: int, name: string, slug: string}
     */
    private function organizationData(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }
}
