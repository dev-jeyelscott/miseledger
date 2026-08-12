<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\SaveStorageLocation;
use App\Enums\OrganizationPermission;
use App\Http\Requests\Organizations\StoreOrganizationStorageLocationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationStorageLocationRequest;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationStorageLocationController extends Controller
{
    /**
     * Show storage locations inside one tenant-scoped restaurant location.
     */
    public function index(
        Organization $organization,
        Location $location,
    ): Response {
        Gate::authorize(
            OrganizationPermission::LocationsManage->value,
            $organization,
        );

        $storageLocations = $location
            ->storageLocations()
            ->orderByDesc('active')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(
                static fn (StorageLocation $storageLocation): array => [
                    'id' => $storageLocation->id,
                    'name' => $storageLocation->name,
                    'code' => $storageLocation->code,
                    'active' => $storageLocation->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render(
            'organizations/locations/storage-locations/index',
            [
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
                'storageLocations' => $storageLocations,
            ],
        );
    }

    /**
     * Create a storage location using server-resolved tenant ownership.
     */
    public function store(
        StoreOrganizationStorageLocationRequest $request,
        Organization $organization,
        Location $location,
        SaveStorageLocation $saveStorageLocation,
    ): RedirectResponse {
        $saveStorageLocation->handle(
            $organization,
            $location,
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Storage location created.'),
        ]);

        return to_route(
            'organizations.locations.storage-locations.index',
            [$organization, $location],
        );
    }

    /**
     * Show the storage-location edit form.
     */
    public function edit(
        Organization $organization,
        Location $location,
        StorageLocation $storageLocation,
    ): Response {
        Gate::authorize(
            OrganizationPermission::LocationsManage->value,
            $organization,
        );

        return Inertia::render(
            'organizations/locations/storage-locations/edit',
            [
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
                'storageLocation' => [
                    'id' => $storageLocation->id,
                    'name' => $storageLocation->name,
                    'code' => $storageLocation->code,
                    'active' => $storageLocation->active,
                ],
            ],
        );
    }

    /**
     * Update a storage location without permitting ownership reassignment.
     */
    public function update(
        UpdateOrganizationStorageLocationRequest $request,
        Organization $organization,
        Location $location,
        StorageLocation $storageLocation,
        SaveStorageLocation $saveStorageLocation,
    ): RedirectResponse {
        $saveStorageLocation->handle(
            $organization,
            $location,
            $request->validated(),
            $storageLocation,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Storage location updated.'),
        ]);

        return to_route(
            'organizations.locations.storage-locations.index',
            [$organization, $location],
        );
    }
}
