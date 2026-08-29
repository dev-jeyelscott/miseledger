<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveUnitOfMeasure;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveUnitOfMeasureRequest;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Support\Inventory\StandardUnits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UnitOfMeasureController extends Controller
{
    /**
     * Show the searchable UOM master for the active organization.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryView->value,
            $organization,
        );

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'dimension' => [
                'nullable',
                'string',
                Rule::in(StandardUnits::dimensions()),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $dimension = isset($validated['dimension'])
            ? (string) $validated['dimension']
            : null;

        $status = isset($validated['status'])
            ? (string) $validated['status']
            : null;

        $unitsQuery = $organization
            ->unitsOfMeasure()
            ->withCount([
                'baseInventoryItems',
                'inventoryItemUnits',
            ]);

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';

            $unitsQuery->where(
                static function (Builder $query) use ($searchPattern): void {
                    $query
                        ->whereLike('name', $searchPattern)
                        ->orWhereLike('symbol', $searchPattern);
                },
            );
        }

        if ($dimension !== null) {
            $unitsQuery->where('dimension', $dimension);
        }

        if ($status !== null) {
            $unitsQuery->where('active', $status === 'active');
        }

        $units = $unitsQuery
            ->orderByDesc('active')
            ->orderBy('dimension')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(
                static fn (UnitOfMeasure $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'dimension' => $unit->dimension,
                    'active' => $unit->active,
                    'usageCount' => (
                        (int) $unit->getAttribute(
                            'base_inventory_items_count',
                        )
                        + (int) $unit->getAttribute(
                            'inventory_item_units_count',
                        )
                    ),
                    'updatedOn' => $unit->updated_at
                        ?->timezone($organization->timezone)
                        ->format('M j, Y'),
                ],
            )
            ->values()
            ->all();

        return Inertia::render('inventory/units/index', [
            'units' => $units,
            'summary' => [
                'total' => $organization
                    ->unitsOfMeasure()
                    ->count(),
                'active' => $organization
                    ->unitsOfMeasure()
                    ->where('active', true)
                    ->count(),
                'dimensions' => $organization
                    ->unitsOfMeasure()
                    ->distinct()
                    ->count('dimension'),
            ],
            'filters' => [
                'search' => $search,
                'dimension' => $dimension,
                'status' => $status,
            ],
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

        if ($request->boolean('_modal')) {
            return back();
        }

        return to_route('inventory.units.index');
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

        return to_route('inventory.units.index');
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
