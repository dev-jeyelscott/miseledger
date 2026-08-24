<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Billing\OrganizationFeatureEntitlement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single reusable feature-entitlement boundary derived from the P2
 * `OrganizationFeatureEntitlement` gate. Blocks every request (read or
 * write) against a route whose configured plan-catalog feature code is
 * not granted to the resolved organization, so disabled purchasing,
 * recipes, reports/exports, or other configured plan features cannot be
 * reached through direct route access even when hidden from navigation.
 *
 * Additive to RBAC: existing `Gate::authorize` role checks inside
 * controllers still run for the request regardless of this middleware.
 */
class EnforceFeatureEntitlement
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $organization = $this->resolveOrganization($request);

        if ($organization === null) {
            return $next($request);
        }

        if (! OrganizationFeatureEntitlement::isGranted($organization, $feature)) {
            abort(403, __('This feature is not included in your current plan.'));
        }

        return $next($request);
    }

    /**
     * Prefer the route-bound organization (routes that act on a specific
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
