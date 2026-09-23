<?php

namespace App\Http\Controllers\Mobile;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The operational-only "More" tab (Spec 9): a pure Inertia render of a menu
 * that links out to existing desktop pages. Current org/location/role
 * summary comes from the shared `organizationContext` prop; this controller
 * adds nothing beyond the shell props `MobileLayout` already needs.
 */
class MoreController extends MobileController
{
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        $location = $this->activeLocation($request);

        return Inertia::render('mobile/more/index', [
            'activeLocation' => $location !== null ? [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ] : null,
            'organization' => $organization !== null
                ? $this->organizationData($organization)
                : null,
            'navBadgeCounts' => $this->navBadgeCounts($request),
        ]);
    }

    /**
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
