<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryCategory;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryCategoryRequest;
use App\Models\InventoryCategory;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryView->value,
            $organization,
        );

        $categories = $organization
            ->inventoryCategories()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (InventoryCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'active' => $category->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('inventory/categories/index', [
            'categories' => $categories,
            'canManage' => Gate::allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            ),
        ]);
    }

    public function store(
        SaveInventoryCategoryRequest $request,
        SaveInventoryCategory $saveInventoryCategory,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        $saveInventoryCategory->handle($organization, [
            'name' => (string) $request->validated('name'),
            'active' => (bool) $request->validated('active'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Inventory category created.'),
        ]);

        return to_route('inventory.categories.index');
    }

    public function edit(
        Request $request,
        string $inventoryCategory,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        $category = $organization
            ->inventoryCategories()
            ->findOrFail($inventoryCategory);

        return Inertia::render('inventory/categories/edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'active' => $category->active,
            ],
        ]);
    }

    public function update(
        SaveInventoryCategoryRequest $request,
        string $inventoryCategory,
        SaveInventoryCategory $saveInventoryCategory,
    ): RedirectResponse {
        $organization = $request->organization();
        $category = $request->inventoryCategory();

        if ($organization === null || $category === null) {
            abort(403);
        }

        $saveInventoryCategory->handle(
            $organization,
            [
                'name' => (string) $request->validated('name'),
                'active' => (bool) $request->validated('active'),
            ],
            $category,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Inventory category updated.'),
        ]);

        if ($request->boolean('_modal')) {
            return back();
        }

        return to_route(
            'inventory.categories.edit',
            $inventoryCategory,
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
