<?php

namespace App\Http\Controllers\Mobile;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends MobileController
{
    /** Render the mobile Home page, or the empty state a new tenant sees before creating a location. */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        if ($organization === null) {
            return Inertia::render('mobile/home', [
                'activeLocation' => null,
                'organization' => null,
                'hasLocations' => false,
                'navBadgeCounts' => [],
            ]);
        }

        $location = $this->requireActiveLocation($request);

        return Inertia::render('mobile/home', [
            'activeLocation' => $location !== null ? [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ] : null,
            'organization' => $this->organizationData($organization),
            'hasLocations' => $organization->locations()
                ->where('active', true)
                ->exists(),
            'navBadgeCounts' => $this->navBadgeCounts($request),
        ]);
    }

    /**
     * Serialize only organization data required by the mobile shell.
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
