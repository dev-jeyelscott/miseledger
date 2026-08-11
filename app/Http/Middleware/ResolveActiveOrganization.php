<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMembership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveOrganization
{
    /**
     * Resolve the active organization only from valid user memberships.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $memberships = $user->organizationMemberships()
            ->whereHas(
                'organization',
                fn ($query) => $query->where('active', true),
            )
            ->with('organization')
            ->orderBy('id')
            ->get();

        $request->attributes->set(
            'organizationMemberships',
            $memberships,
        );

        $activeOrganizationId = (int) $request->session()->get(
            'active_organization_id',
            0,
        );

        $activeMembership = $memberships->first(
            fn (OrganizationMembership $membership): bool => (
                $membership->organization_id === $activeOrganizationId
            ),
        );

        if ($activeMembership === null) {
            $activeMembership = $memberships->first();
        }

        if ($activeMembership === null) {
            $request->session()->forget('active_organization_id');
            $request->attributes->set('activeOrganization', null);

            return $next($request);
        }

        $request->session()->put(
            'active_organization_id',
            $activeMembership->organization_id,
        );

        $request->attributes->set(
            'activeOrganization',
            $activeMembership->organization,
        );

        return $next($request);
    }
}
