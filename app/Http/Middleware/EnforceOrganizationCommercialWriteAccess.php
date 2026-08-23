<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Billing\OrganizationSubscriptionAccessResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single reusable commercial-write boundary derived from the P2 organization
 * subscription access resolver. Blocks mutating requests against a
 * commercially `ReadOnly` organization while leaving safe (GET/HEAD/OPTIONS)
 * requests untouched, so reports, history, and other reads keep working
 * whenever existing RBAC already permits them.
 *
 * Deliberately independent from `Organization.active`: administrative
 * deactivation continues to be decided elsewhere via existing RBAC/Gate
 * checks, which still run for the request regardless of this middleware.
 */
class EnforceOrganizationCommercialWriteAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $organization = $this->resolveOrganization($request);

        if ($organization === null) {
            return $next($request);
        }

        $access = OrganizationSubscriptionAccessResolver::resolve($organization);

        if ($access->isReadOnly()) {
            abort(403, __('This organization is read-only until its subscription is resolved.'));
        }

        return $next($request);
    }

    /**
     * Prefer the route-bound organization (routes that mutate a specific
     * tenant by URL parameter) and fall back to the session-resolved active
     * organization (routes that only ever act on the active tenant).
     */
    private function resolveOrganization(Request $request): ?Organization
    {
        $routeOrganization = $request->route('organization');

        if ($routeOrganization instanceof Organization) {
            return $routeOrganization;
        }

        $activeOrganization = $request->attributes->get('activeOrganization');

        return $activeOrganization instanceof Organization ? $activeOrganization : null;
    }
}
