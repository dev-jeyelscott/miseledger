<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryBrand;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryBrandRequest;
use App\Models\InventoryBrand;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryBrandController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryView->value,
            $organization,
        );

        $brands = $organization
            ->inventoryBrands()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (InventoryBrand $brand): array => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'active' => $brand->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('inventory/brands/index', [
            'brands' => $brands,
            'canManage' => Gate::allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            ),
        ]);
    }

    public function store(
        SaveInventoryBrandRequest $request,
        SaveInventoryBrand $saveInventoryBrand,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        $saveInventoryBrand->handle($organization, [
            'name' => (string) $request->validated('name'),
            'active' => (bool) $request->validated('active'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Inventory brand created.'),
        ]);

        return to_route('inventory.brands.index');
    }

    public function edit(
        Request $request,
        string $inventoryBrand,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        $brand = $organization
            ->inventoryBrands()
            ->findOrFail($inventoryBrand);

        return Inertia::render('inventory/brands/edit', [
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'active' => $brand->active,
            ],
        ]);
    }

    public function update(
        SaveInventoryBrandRequest $request,
        string $inventoryBrand,
        SaveInventoryBrand $saveInventoryBrand,
    ): RedirectResponse {
        $organization = $request->organization();
        $brand = $request->inventoryBrand();

        if ($organization === null || $brand === null) {
            abort(403);
        }

        $saveInventoryBrand->handle(
            $organization,
            [
                'name' => (string) $request->validated('name'),
                'active' => (bool) $request->validated('active'),
            ],
            $brand,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Inventory brand updated.'),
        ]);

        if ($request->boolean('_modal')) {
            return back();
        }

        return to_route(
            'inventory.brands.edit',
            $inventoryBrand,
        );
    }

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
