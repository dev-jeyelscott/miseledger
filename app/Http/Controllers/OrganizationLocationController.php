<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\EnsureStockTransferDependencyCanBeDeactivated;
use App\Enums\OrganizationPermission;
use App\Http\Requests\Organizations\StoreOrganizationLocationRequest;
use App\Http\Requests\Organizations\UpdateOrganizationLocationRequest;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationLocationController extends Controller
{
    /** Show locations belonging to an organization. */
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
                static fn (Location $location): array => [
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

    /** Create a location and its default storage area atomically. */
    public function store(
        StoreOrganizationLocationRequest $request,
        Organization $organization,
    ): RedirectResponse {
        DB::transaction(function () use (
            $request,
            $organization,
        ): void {
            $location = $organization
                ->locations()
                ->create($request->validated());

            $storageLocation = new StorageLocation([
                'name' => StorageLocation::DEFAULT_NAME,
                'code' => StorageLocation::DEFAULT_CODE,
                'active' => true,
            ]);

            $storageLocation
                ->organization()
                ->associate($organization);

            $storageLocation
                ->location()
                ->associate($location);

            $storageLocation->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Location created.'),
        ]);

        return to_route(
            'organizations.locations.index',
            $organization,
        );
    }

    /** Show the location editing form. */
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
        EnsureStockTransferDependencyCanBeDeactivated $ensureStockTransferDependencyCanBeDeactivated,
    ): RedirectResponse {
        /** @var array{name: string, code: string, active: bool} $attributes */
        $attributes = $request->validated();

        DB::transaction(function () use (
            $organization,
            $location,
            $attributes,
            $ensureStockTransferDependencyCanBeDeactivated,
        ): void {
            if (! $attributes['active']) {
                $ensureStockTransferDependencyCanBeDeactivated
                    ->assertLocationCanBeDeactivated(
                        $organization,
                        $location,
                    );
            }

            $lockedLocation = Location::query()
                ->where(
                    'organization_id',
                    $organization->getKey(),
                )
                ->whereKey($location->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedLocation->update($attributes);
        });

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
