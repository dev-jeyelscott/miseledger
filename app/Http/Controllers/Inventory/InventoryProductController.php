<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryProduct;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryProductRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryProductController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(OrganizationPermission::InventoryView->value, $organization);

        return Inertia::render('inventory/product-families/index', [
            'productFamilies' => $organization->inventoryProducts()
                ->withCount('inventoryItems')
                ->orderByDesc('active')
                ->orderBy('name')
                ->get()
                ->map(static fn ($product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'active' => $product->active,
                    'variantCount' => $product->inventory_items_count,
                ])
                ->values()
                ->all(),
            'canManage' => Gate::allows(OrganizationPermission::InventoryAdjust->value, $organization),
        ]);
    }

    public function show(Request $request, string $inventoryProduct): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(OrganizationPermission::InventoryView->value, $organization);

        $product = $organization
            ->inventoryProducts()
            ->with([
                'options' => fn ($query) => $query->with('values')->orderBy('name'),
                'inventoryItems' => fn ($query) => $query->with([
                    'baseUnitOfMeasure:id,name,symbol,active',
                    'inventoryBrand:id,name,active',
                    'barcodes' => fn ($barcodeQuery) => $barcodeQuery
                        ->where('primary', true)
                        ->where('active', true),
                ])->orderByDesc('active')->orderBy('name'),
            ])
            ->findOrFail($inventoryProduct);

        return Inertia::render('inventory/product-families/show', [
            'productFamily' => [
                'id' => $product->id,
                'name' => $product->name,
                'active' => $product->active,
                'options' => $product->options->map(static fn ($option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'active' => $option->active,
                    'values' => $option->values->sortBy('value')->map(static fn ($value): array => [
                        'id' => $value->id,
                        'value' => $value->value,
                        'active' => $value->active,
                    ])->values()->all(),
                ])->values()->all(),
                'variants' => $product->inventoryItems->map(static fn ($item): array => [
                    'id' => $item->id,
                    'description' => $item->description ?? $item->name,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'barcode' => $item->barcodes->first()?->barcode,
                    'baseUnitOfMeasure' => [
                        'id' => $item->baseUnitOfMeasure->id,
                        'name' => $item->baseUnitOfMeasure->name,
                        'symbol' => $item->baseUnitOfMeasure->symbol,
                    ],
                    'brand' => $item->inventoryBrand === null ? null : [
                        'id' => $item->inventoryBrand->id,
                        'name' => $item->inventoryBrand->name,
                    ],
                    'active' => $item->active,
                ])->values()->all(),
            ],
            'canManage' => Gate::allows(OrganizationPermission::InventoryAdjust->value, $organization),
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
