<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Support\Billing\OrganizationSubscriptionAccess;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use App\Support\Billing\PlanCatalog;
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
     *     }>,
     *     subscription: array{
     *         plan: string|null,
     *         status: string|null,
     *         accessMode: string,
     *         onTrial: bool,
     *         trialEndsAt: string|null,
     *         endsAt: string|null,
     *         billingWarning: bool
     *     }|null,
     *     entitlements: array{
     *         features: list<string>,
     *         limits: array<string, int|null>
     *     }|null
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

        $access = $activeOrganization instanceof Organization
            ? OrganizationSubscriptionAccessResolver::resolve($activeOrganization)
            : null;

        return [
            'active' => $activeOrganization instanceof Organization
                ? $this->organizationData($activeOrganization)
                : null,
            'memberships' => $membershipData,
            'subscription' => $access !== null ? $this->subscriptionData($access) : null,
            'entitlements' => $access !== null ? $this->entitlementData($access) : null,
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

    /**
     * Serialize only safe commercial state for the active organization.
     * Never exposes Stripe secrets, customer identifiers, payment-method
     * tokens, or raw Cashier/Stripe objects.
     *
     * @return array{
     *     plan: string|null,
     *     status: string|null,
     *     accessMode: string,
     *     onTrial: bool,
     *     trialEndsAt: string|null,
     *     endsAt: string|null,
     *     billingWarning: bool
     * }
     */
    private function subscriptionData(OrganizationSubscriptionAccess $access): array
    {
        return [
            'plan' => $access->plan?->value,
            'status' => $access->subscriptionStatus,
            'accessMode' => $access->accessMode->value,
            'onTrial' => $access->onTrial,
            'trialEndsAt' => $access->trialEndsAt?->toISOString(),
            'endsAt' => $access->endsAt?->toISOString(),
            'billingWarning' => $access->billingWarning,
        ];
    }

    /**
     * Serialize only the plan's declared feature codes and quantitative
     * limits for the active organization, using the configuration-owned
     * plan catalog rather than any Stripe usage/metering data.
     *
     * @return array{features: list<string>, limits: array<string, int|null>}
     */
    private function entitlementData(OrganizationSubscriptionAccess $access): array
    {
        $definition = $access->plan !== null
            ? (new PlanCatalog)->get($access->plan)
            : null;

        if ($definition === null) {
            return ['features' => [], 'limits' => []];
        }

        return [
            'features' => $definition->features,
            'limits' => $definition->limits,
        ];
    }
}
