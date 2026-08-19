<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveUnitOfMeasure;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveUnitOfMeasureRequest;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UnitOfMeasureController extends Controller
{
    /**
     * Show the active organization's UOM master.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryView->value,
            $organization,
        );

        $units = $organization
            ->unitsOfMeasure()
            ->orderByDesc('active')
            ->orderBy('dimension')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (UnitOfMeasure $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'dimension' => $unit->dimension,
                    'active' => $unit->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('inventory/units/index', [
            'units' => $units,
            'canManage' => Gate::allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            ),
        ]);
    }

    /**
     * Create a new organization-scoped UOM.
     */
    public function store(
        SaveUnitOfMeasureRequest $request,
        SaveUnitOfMeasure $saveUnitOfMeasure,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        $saveUnitOfMeasure->handle(
            $organization,
            [
                'name' => (string) $request->validated('name'),
                'symbol' => (string) $request->validated('symbol'),
                'dimension' => (string) $request->validated('dimension'),
                'active' => (bool) $request->validated('active'),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Unit of measure created.'),
        ]);

        return to_route('inventory.units.index');
    }

    /**
     * Show the UOM edit screen.
     */
    public function edit(
        Request $request,
        string $unitOfMeasure,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        $unit = $organization
            ->unitsOfMeasure()
            ->findOrFail($unitOfMeasure);

        return Inertia::render('inventory/units/edit', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
                'dimension' => $unit->dimension,
                'active' => $unit->active,
            ],
        ]);
    }

    /**
     * Update an existing tenant-scoped UOM.
     */
    public function update(
        SaveUnitOfMeasureRequest $request,
        string $unitOfMeasure,
        SaveUnitOfMeasure $saveUnitOfMeasure,
    ): RedirectResponse {
        $organization = $request->organization();
        $unit = $request->unitOfMeasure();

        if ($organization === null || $unit === null) {
            abort(403);
        }

        $saveUnitOfMeasure->handle(
            $organization,
            [
                'name' => (string) $request->validated('name'),
                'symbol' => (string) $request->validated('symbol'),
                'dimension' => (string) $request->validated('dimension'),
                'active' => (bool) $request->validated('active'),
            ],
            $unit,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Unit of measure updated.'),
        ]);

        if ($request->boolean('_modal')) {
            return back();
        }

        return to_route(
            'inventory.units.edit',
            $unitOfMeasure,
        );
    }

    /**
     * Return the active organization resolved by tenancy middleware.
     */
    private function activeOrganization(Request $request): Organization
    {
        $organization = $request->attributes->get(
            'activeOrganization',
        );

        if (! $organization instanceof Organization) {
            abort(403);
        }

        return $organization;
    }
}
