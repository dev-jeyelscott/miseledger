<?php

namespace App\Http\Controllers\Mobile;

use App\Enums\OrganizationPermission;
use App\Http\Requests\Mobile\SelectMobileLocationRequest;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends MobileController
{
    /** Build the picker URL for a given post-selection redirect target. */
    public static function pickerUrl(string $next): string
    {
        return route('mobile.location.index', ['next' => $next]);
    }

    /** List active locations of the active organization as large tap targets. */
    public function index(Request $request): Response|RedirectResponse
    {
        $organization = $this->activeOrganization($request);

        if ($organization === null) {
            return redirect()->route('dashboard');
        }

        $locations = $organization->locations()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $activeLocation = $this->activeLocation($request);
        $user = $request->user();

        return Inertia::render('mobile/location-picker', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'activeLocation' => $activeLocation !== null ? [
                'id' => $activeLocation->id,
                'name' => $activeLocation->name,
                'code' => $activeLocation->code,
            ] : null,
            'locationOptions' => $locations->map(
                static fn (Location $location): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'code' => $location->code,
                ],
            )->values()->all(),
            'next' => is_string($request->query('next'))
                ? $request->query('next')
                : null,
            'canManageLocations' => $user instanceof User
                && Gate::forUser($user)->allows(
                    OrganizationPermission::LocationsManage->value,
                    $organization,
                ),
        ]);
    }

    /** Persist the selected location and redirect to the validated `next` target. */
    public function store(SelectMobileLocationRequest $request): RedirectResponse
    {
        $organization = $this->activeOrganization($request);

        abort_if($organization === null, 404);

        $request->session()->put(
            "mobile_location_by_org.{$organization->id}",
            (int) $request->validated('location_id'),
        );

        return redirect()->to($request->safeNext());
    }
}
