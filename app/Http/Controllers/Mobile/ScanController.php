<?php

namespace App\Http\Controllers\Mobile;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends MobileController
{
    /** Render the persistent scanner shell; lookups happen via JSON XHR so the camera stream survives repeated scans. */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);
        $location = $this->requireActiveLocation($request);

        return Inertia::render('mobile/scan/index', [
            'activeLocation' => $location !== null ? [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
            ] : null,
            'organization' => $organization !== null ? [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ] : null,
            'navBadgeCounts' => $this->navBadgeCounts($request),
        ]);
    }
}
