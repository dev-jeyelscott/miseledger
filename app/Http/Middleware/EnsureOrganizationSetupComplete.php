<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Onboarding\OrganizationSetupReadiness;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side setup blocker for operational stock workflows (purchasing,
 * receiving, counts, transfers, waste, and adjustments).
 *
 * Reads always pass so normal navigation keeps working while setup is
 * incomplete. Mutations are blocked until the active organization meets
 * minimum operational setup. Setup's own mutations (locations, units, items,
 * opening stock, suppliers, members) are intentionally not behind this gate.
 */
class EnsureOrganizationSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $organization = $request->attributes->get('activeOrganization');

        if (
            ! $organization instanceof Organization
            || OrganizationSetupReadiness::isReady($organization)
        ) {
            return $next($request);
        }

        $message = __('Finish organization setup before recording stock activity. Add a location and inventory items, then resolve their opening stock.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 409);
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return redirect()->route('onboarding.show');
    }
}
