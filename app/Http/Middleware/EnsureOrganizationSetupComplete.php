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

        $message = $this->blockedMessage($organization);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 409);
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return redirect()->route('onboarding.show');
    }

    /**
     * Build a recovery message naming only the setup condition(s) the
     * organization actually still has unmet, instead of a static checklist
     * that may misdirect recovery toward requirements already satisfied.
     */
    private function blockedMessage(Organization $organization): string
    {
        $status = OrganizationSetupReadiness::resolve($organization);

        $missing = [];

        if ($status->activeLocationCount === 0) {
            $missing[] = __('an active location');
        }

        if ($status->activeItemCount === 0) {
            $missing[] = __('an active inventory item');
        }

        if ($status->unresolvedOpeningStockCount > 0) {
            $missing[] = __('opening stock resolved for every active inventory item');
        }

        if ($missing === []) {
            return __('Finish organization setup before recording stock activity.');
        }

        return __('Finish organization setup before recording stock activity. This organization still needs: :missing.', [
            'missing' => implode(', ', $missing),
        ]);
    }
}
