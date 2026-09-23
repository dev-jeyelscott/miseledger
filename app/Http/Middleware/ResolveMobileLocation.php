<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Mobile\LocationController;
use App\Models\Location;
use App\Models\Organization;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ResolveMobileLocation
{
    /**
     * Resolve the remembered mobile `Location` for the active organization.
     *
     * Implements locked decisions #26 (stale remembered location silently
     * falls back to the first accessible active location, with a flashed
     * notice) and #33 (no remembered location yet forces a hard redirect to
     * the picker before any other `mobile.*` page renders). An organization
     * with zero active locations is a degenerate third case: the request is
     * let through with `mobileActiveLocation` null so `HomeController` (and
     * `MobileController::requireActiveLocation()`) can render the
     * "no locations configured" empty state instead of looping to an empty
     * picker.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $activeOrganization = $request->attributes->get('activeOrganization');

        if (! $activeOrganization instanceof Organization) {
            $request->attributes->set('mobileActiveLocation', null);

            return $next($request);
        }

        $sessionKey = 'mobile_location_by_org';
        $rememberedByOrg = $request->session()->get($sessionKey, []);
        $rememberedLocationId = is_array($rememberedByOrg)
            ? ($rememberedByOrg[$activeOrganization->id] ?? null)
            : null;

        if ($rememberedLocationId !== null) {
            $location = Location::query()
                ->where('organization_id', $activeOrganization->id)
                ->where('active', true)
                ->find($rememberedLocationId);

            if ($location !== null) {
                $request->attributes->set('mobileActiveLocation', $location);

                return $next($request);
            }
        }

        $firstAccessibleLocation = Location::query()
            ->where('organization_id', $activeOrganization->id)
            ->where('active', true)
            ->orderBy('name')
            ->first();

        if ($firstAccessibleLocation === null) {
            $request->attributes->set('mobileActiveLocation', null);

            return $next($request);
        }

        if ($rememberedLocationId !== null) {
            $request->session()->put(
                "{$sessionKey}.{$activeOrganization->id}",
                $firstAccessibleLocation->id,
            );

            $request->attributes->set(
                'mobileActiveLocation',
                $firstAccessibleLocation,
            );

            Inertia::flash('toast', [
                'type' => 'info',
                'message' => "Your last location is no longer available. Switched to {$firstAccessibleLocation->name}.",
            ]);

            return $next($request);
        }

        if ($request->routeIs('mobile.location.*')) {
            $request->attributes->set('mobileActiveLocation', null);

            return $next($request);
        }

        return $this->redirectToPicker($request);
    }

    /** Redirect to the location picker, preserving the originally requested mobile path. */
    private function redirectToPicker(Request $request): RedirectResponse
    {
        $next = '/'.ltrim($request->getRequestUri(), '/');

        return redirect()->to(LocationController::pickerUrl($next));
    }
}
