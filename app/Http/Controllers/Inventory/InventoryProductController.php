<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryProduct;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryProductRequest;
use App\Models\InventoryItem;
use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\InventoryProductOptionValue;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryProductController extends Controller
{
    /**
     * Show product families for the active organization.
     */
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
                ->map(static fn (InventoryProduct $product): array => [
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

    /**
     * Show one product family with its controlled options and variants.
     */
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
                'options' => $product->options->map(static fn (InventoryProductOption $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'active' => $option->active,
                    'values' => array_values(
                        $option->values
                            ->sortBy('value')
                            ->map(static fn (InventoryProductOptionValue $value): array => [
                                'id' => $value->id,
                                'value' => $value->value,
                                'active' => $value->active,
                            ])
                            ->all(),
                    ),
                ])->values()->all(),
                'variants' => $product->inventoryItems->map(static fn (InventoryItem $item): array => [
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

    /**
     * Create a product family in the active organization.
     */
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

    /**
     * Update a product family owned by the active organization.
     */
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
