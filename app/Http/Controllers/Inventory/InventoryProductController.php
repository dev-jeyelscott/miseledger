<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryProduct;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryProductRequest;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InventoryProductController extends Controller
{
    public function show(
        Request $request,
        string $inventoryProduct,
    ): JsonResponse {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryView->value,
            $organization,
        );

        $product = $organization
            ->inventoryProducts()
            ->findOrFail($inventoryProduct);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'active' => $product->active,
        ]);
    }

    public function store(
        SaveInventoryProductRequest $request,
        SaveInventoryProduct $saveInventoryProduct,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        $saveInventoryProduct->handle($organization, [
            'name' => (string) $request->validated('name'),
            'active' => (bool) $request->validated('active'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Product family created.'),
        ]);

        return back();
    }

    public function update(
        SaveInventoryProductRequest $request,
        string $inventoryProduct,
        SaveInventoryProduct $saveInventoryProduct,
    ): RedirectResponse {
        $organization = $request->organization();
        $product = $request->inventoryProduct();

        if ($organization === null || $product === null) {
            abort(403);
        }

        $saveInventoryProduct->handle(
            $organization,
            [
                'name' => (string) $request->validated('name'),
                'active' => (bool) $request->validated('active'),
            ],
            $product,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Product family updated.'),
        ]);

        return back();
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
