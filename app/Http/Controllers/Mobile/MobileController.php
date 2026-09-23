<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use App\Support\Mobile\MobileTaskAggregator;
use Illuminate\Http\Request;

abstract class MobileController extends Controller
{
    /** Resolve the active organization already set by `ResolveActiveOrganization`. */
    protected function activeOrganization(Request $request): ?Organization
    {
        $organization = $request->attributes->get('activeOrganization');

        return $organization instanceof Organization ? $organization : null;
    }

    /** Resolve the active mobile location already set by `ResolveMobileLocation`. */
    protected function activeLocation(Request $request): ?Location
    {
        $location = $request->attributes->get('mobileActiveLocation');

        return $location instanceof Location ? $location : null;
    }

    /**
     * Defense-in-depth guard for every location-dependent mobile action.
     *
     * Under normal operation `ResolveMobileLocation` has already guaranteed
     * a location exists by the time a controller runs. This returns null
     * only for the zero-active-locations edge case, or if a future
     * `mobile.*` route forgets to run behind the middleware; callers must
     * treat null as "render the no-locations-configured empty state".
     */
    protected function requireActiveLocation(Request $request): ?Location
    {
        return $this->activeLocation($request);
    }

    /**
     * Bottom-nav badge counts, read once per page load from the same
     * aggregator the Home and Tasks pages use (Spec 7 Behavior step 4).
     * No separate polling endpoint: no real-time push in an online-first,
     * no-background-sync scope.
     *
     * @return array<string, int>
     */
    protected function navBadgeCounts(Request $request): array
    {
        $organization = $this->activeOrganization($request);
        $location = $this->activeLocation($request);
        $actor = $request->user();

        if ($organization === null || $location === null || ! $actor instanceof User) {
            return [];
        }

        $count = app(MobileTaskAggregator::class)
            ->forLocation($organization, $location, $actor)
            ->count();

        return ['tasks' => $count];
    }
}
