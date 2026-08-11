<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationPermission;
use App\Http\Requests\Organizations\StoreOrganizationLocationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationLocationRequest;
use App\Models\Location;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationLocationController extends Controller
{
    /**
     * Show locations belonging to an organization.
     */
    public function index(Organization $organization): Response
    {
        Gate::authorize(
            OrganizationPermission::LocationsManage->value,
            $organization,
        );

        $locations = $organization->locations()
            ->orderByDesc('active')
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'active',
            ])
            ->map(
                static fn(Location $location): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'code' => $location->code,
                    'active' => $location->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('organizations/locations/index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'locations' => $locations,
        ]);
    }

    /**
     * Create a location through the owning organization relationship.
     */
    public function store(
        StoreOrganizationLocationRequest $request,
        Organization $organization,
    ): RedirectResponse {
        $organization->locations()->create(
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Location created.'),
        ]);

        return to_route(
            'organizations.locations.index',
            $organization,
        );
    }

    /**
     * Show the location editing form.
     */
    public function edit(
        Organization $organization,
        Location $location,
    ): Response {
        Gate::authorize(
            OrganizationPermission::LocationsManage->value,
            $organization,
        );

        return Inertia::render('organizations/locations/edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'active' => $location->active,
            ],
        ]);
    }

    /**
     * Update mutable location configuration without changing tenant ownership.
     */
    public function update(
        UpdateOrganizationLocationRequest $request,
        Organization $organization,
        Location $location,
    ): RedirectResponse {
        $location->update(
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Location updated.'),
        ]);

        return to_route(
            'organizations.locations.index',
            $organization,
        );
    }
}
