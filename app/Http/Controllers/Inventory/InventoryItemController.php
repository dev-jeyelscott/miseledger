<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SaveInventoryItem;
use App\Enums\InventoryItemType;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveInventoryItemRequest;
use App\Models\InventoryBrand;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryItemController extends Controller
{
    /**
     * Show the searchable and paginated inventory master for the active organization.
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
            'category' => ['nullable', 'integer', 'min:1'],
            'brand' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', Rule::enum(InventoryItemType::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['name', 'sku', 'type', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $categoryId = isset($validated['category'])
            ? (int) $validated['category']
            : null;
        $brandId = isset($validated['brand'])
            ? (int) $validated['brand']
            : null;
        $type = isset($validated['type'])
            ? (string) $validated['type']
            : null;
        $status = isset($validated['status'])
            ? (string) $validated['status']
            : null;
        $sort = isset($validated['sort'])
            ? (string) $validated['sort']
            : null;
        $direction = ($validated['direction'] ?? 'asc') === 'desc'
            ? 'desc'
            : 'asc';

        $itemsQuery = $organization
            ->inventoryItems()
            ->with([
                'baseUnitOfMeasure:id,name,symbol,active',
                'inventoryCategory:id,name,active',
                'inventoryBrand:id,name,active',
            ])
            ->withCount('unitConversions');

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';

            $itemsQuery->where(
                static function (Builder $query) use ($searchPattern): void {
                    $query
                        ->whereLike('name', $searchPattern)
                        ->orWhereLike('sku', $searchPattern)
                        ->orWhereLike('model_number', $searchPattern)
                        ->orWhereLike('manufacturer_part_number', $searchPattern)
                        ->orWhereHas(
                            'barcodes',
                            static function (Builder $barcodes) use ($searchPattern): void {
                                $barcodes->whereLike('barcode', $searchPattern);
                            },
                        );
                },
            );
        }

        if ($categoryId !== null) {
            $itemsQuery->where('inventory_category_id', $categoryId);
        }

        if ($brandId !== null) {
            $itemsQuery->where('inventory_brand_id', $brandId);
        }

        if ($type !== null) {
            $itemsQuery->where('type', $type);
        }

        if ($status !== null) {
            $itemsQuery->where('active', $status === 'active');
        }

        if ($sort === null) {
            $itemsQuery
                ->orderByDesc('active')
                ->orderBy('name')
                ->orderBy('id');
        } else {
            $sortColumn = match ($sort) {
                'sku' => 'sku',
                'type' => 'type',
                'status' => 'active',
                default => 'name',
            };

            $itemsQuery->orderBy($sortColumn, $direction);

            if ($sortColumn !== 'name') {
                $itemsQuery->orderBy('name');
            }

            $itemsQuery->orderBy('id');
        }

        $paginatedItems = $itemsQuery
            ->paginate(25)
            ->withQueryString()
            ->through(
                static fn (InventoryItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'type' => $item->type->value,
                    'yieldPercentage' => $item->yield_percentage,
                    'active' => $item->active,
                    'conversionCount' => (
                        $item->unit_conversions_count ?? 0
                    ),
                    'baseUnitOfMeasure' => [
                        'id' => $item->baseUnitOfMeasure->id,
                        'name' => $item->baseUnitOfMeasure->name,
                        'symbol' => $item->baseUnitOfMeasure->symbol,
                        'active' => $item->baseUnitOfMeasure->active,
                    ],
                    'inventoryCategory' => $item->inventoryCategory === null
                        ? null
                        : [
                            'id' => $item->inventoryCategory->id,
                            'name' => $item->inventoryCategory->name,
                            'active' => $item->inventoryCategory->active,
                        ],
                    'inventoryBrand' => $item->inventoryBrand === null
                        ? null
                        : [
                            'id' => $item->inventoryBrand->id,
                            'name' => $item->inventoryBrand->name,
                            'active' => $item->inventoryBrand->active,
                        ],
                ],
            );

        $categoryOptions = $organization
            ->inventoryCategories()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'active',
            ])
            ->map(
                static fn (InventoryCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'active' => $category->active,
                ],
            )
            ->values()
            ->all();

        $brandOptions = $organization
            ->inventoryBrands()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'active',
            ])
            ->map(
                static fn (InventoryBrand $brand): array => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'active' => $brand->active,
                ],
            )
            ->values()
            ->all();

        $canManage = Gate::allows(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        return Inertia::render('inventory/items/index', [
            'items' => $paginatedItems->items(),
            'pagination' => [
                'current_page' => $paginatedItems->currentPage(),
                'from' => $paginatedItems->firstItem(),
                'last_page' => $paginatedItems->lastPage(),
                'next_page_url' => $paginatedItems->nextPageUrl(),
                'per_page' => $paginatedItems->perPage(),
                'prev_page_url' => $paginatedItems->previousPageUrl(),
                'to' => $paginatedItems->lastItem(),
                'total' => $paginatedItems->total(),
            ],
            'summary' => [
                'total' => $organization->inventoryItems()->count(),
                'active' => $organization
                    ->inventoryItems()
                    ->where('active', true)
                    ->count(),
            ],
            'categoryOptions' => $categoryOptions,
            'brandOptions' => $brandOptions,
            'createUnitOptions' => $canManage
                ? $this->activeUnitOptions($organization)
                : [],
            'filters' => [
                'search' => $search,
                'categoryId' => $categoryId,
                'brandId' => $brandId,
                'type' => $type,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'canManage' => $canManage,
        ]);
    }

    /**
     * Show the inventory-item creation form.
     */
    public function create(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        return Inertia::render('inventory/items/create', [
            'units' => $this->activeUnitOptions($organization),
            'categories' => $this->activeCategoryOptions($organization),
            'brands' => $this->activeBrandOptions($organization),
        ]);
    }

    /**
     * Persist a new inventory item.
     */
    public function store(
        SaveInventoryItemRequest $request,
        SaveInventoryItem $saveInventoryItem,
    ): RedirectResponse {
        $organization = $request->organization();

        if ($organization === null) {
            abort(403);
        }

        $item = $saveInventoryItem->handle(
            $organization,
            [
                'name' => (string) $request->validated('name'),
                'sku' => (string) $request->validated('sku'),
                'base_unit_of_measure_id' => (int) $request->validated(
                    'base_unit_of_measure_id',
                ),
                'inventory_category_id' => $request->validated(
                    'inventory_category_id',
                ),
                'inventory_brand_id' => $request->validated(
                    'inventory_brand_id',
                ),
                'model_number' => $request->validated('model_number'),
                'manufacturer_part_number' => $request->validated(
                    'manufacturer_part_number',
                ),
                'description' => $request->validated('description'),
                'type' => InventoryItemType::from((string) $request->validated(
                    'type',
                )),
                'yield_percentage' => (string) $request->validated(
                    'yield_percentage',
                ),
                'active' => (bool) $request->validated('active'),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Inventory item created.'),
        ]);

        if ($request->boolean('_modal')) {
            return back();
        }

        return to_route('inventory.items.edit', $item);
    }

    /**
     * Show inventory-item and alternate-UOM configuration.
     */
    public function edit(
        Request $request,
        string $inventoryItem,
    ): Response {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::InventoryAdjust->value,
            $organization,
        );

        $item = $organization
            ->inventoryItems()
            ->with([
                'baseUnitOfMeasure:id,name,symbol,active',
                'inventoryCategory:id,name,active',
                'inventoryBrand:id,name,active',
                'unitConversions' => fn ($query) => $query
                    ->with('unitOfMeasure:id,name,symbol,active')
                    ->orderBy('id'),
                'barcodes' => fn ($query) => $query
                    ->with('inventoryItemUnit.unitOfMeasure:id,name,symbol,active')
                    ->orderByDesc('primary')
                    ->orderBy('id'),
            ])
            ->findOrFail($inventoryItem);

        $activeUnits = UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $usedUnitIds = $item
            ->unitConversions
            ->pluck('unit_of_measure_id')
            ->all();

        $availableConversionUnits = $activeUnits
            ->reject(
                static fn (UnitOfMeasure $unit): bool => (
                    $unit->id === $item->base_unit_of_measure_id
                    || in_array($unit->id, $usedUnitIds, true)
                ),
            )
            ->map(
                static fn (UnitOfMeasure $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'active' => $unit->active,
                ],
            )
            ->values()
            ->all();

        return Inertia::render('inventory/items/edit', [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'type' => $item->type->value,
                'yieldPercentage' => $item->yield_percentage,
                'modelNumber' => $item->model_number,
                'manufacturerPartNumber' => $item->manufacturer_part_number,
                'description' => $item->description,
                'active' => $item->active,
                'baseUnitOfMeasure' => [
                    'id' => $item->baseUnitOfMeasure->id,
                    'name' => $item->baseUnitOfMeasure->name,
                    'symbol' => $item->baseUnitOfMeasure->symbol,
                    'active' => $item->baseUnitOfMeasure->active,
                ],
                'inventoryCategory' => $item->inventoryCategory === null
                    ? null
                    : [
                        'id' => $item->inventoryCategory->id,
                        'name' => $item->inventoryCategory->name,
                        'active' => $item->inventoryCategory->active,
                    ],
                'inventoryBrand' => $item->inventoryBrand === null
                    ? null
                    : [
                        'id' => $item->inventoryBrand->id,
                        'name' => $item->inventoryBrand->name,
                        'active' => $item->inventoryBrand->active,
                    ],
                'unitConversions' => $item
                    ->unitConversions
                    ->map(
                        static fn (
                            InventoryItemUnit $conversion,
                        ): array => [
                            'id' => $conversion->id,
                            'quantityInBaseUnit' => (
                                $conversion->quantity_in_base_unit
                            ),
                            'active' => $conversion->active,
                            'unitOfMeasure' => [
                                'id' => $conversion->unitOfMeasure->id,
                                'name' => $conversion
                                    ->unitOfMeasure
                                    ->name,
                                'symbol' => $conversion
                                    ->unitOfMeasure
                                    ->symbol,
                                'active' => $conversion
                                    ->unitOfMeasure
                                    ->active,
                            ],
                        ],
                    )
                    ->values()
                    ->all(),
                'barcodes' => $item
                    ->barcodes
                    ->map(
                        static fn (
                            InventoryItemBarcode $barcode,
                        ): array => [
                            'id' => $barcode->id,
                            'value' => $barcode->barcode,
                            'symbology' => $barcode->symbology->value,
                            'isPrimary' => $barcode->primary,
                            'active' => $barcode->active,
                            'inventoryItemUnit' => $barcode
                                ->inventoryItemUnit === null
                                ? null
                                : [
                                    'id' => $barcode->inventoryItemUnit->id,
                                    'unitOfMeasure' => [
                                        'id' => $barcode
                                            ->inventoryItemUnit
                                            ->unitOfMeasure
                                            ->id,
                                        'name' => $barcode
                                            ->inventoryItemUnit
                                            ->unitOfMeasure
                                            ->name,
                                        'symbol' => $barcode
                                            ->inventoryItemUnit
                                            ->unitOfMeasure
                                            ->symbol,
                                        'active' => $barcode
                                            ->inventoryItemUnit
                                            ->unitOfMeasure
                                            ->active,
                                    ],
                                ],
                        ],
                    )
                    ->values()
                    ->all(),
            ],
            'units' => $activeUnits
                ->map(
                    static fn (UnitOfMeasure $unit): array => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                        'symbol' => $unit->symbol,
                        'active' => $unit->active,
                    ],
                )
                ->values()
                ->all(),
            'categories' => $this->categoryOptionsForItem(
                $organization,
                $item,
            ),
            'brands' => $this->brandOptionsForItem(
                $organization,
                $item,
            ),
            'availableConversionUnits' => (
                $availableConversionUnits
            ),
        ]);
    }

    /**
     * Update an inventory item.
     */
    public function update(
        SaveInventoryItemRequest $request,
        string $inventoryItem,
        SaveInventoryItem $saveInventoryItem,
    ): RedirectResponse {
        $organization = $request->organization();
        $item = $request->inventoryItem();

        if ($organization === null || $item === null) {
            abort(403);
        }

        $saveInventoryItem->handle(
            $organization,
            [
                'name' => (string) $request->validated('name'),
                'sku' => (string) $request->validated('sku'),
                'base_unit_of_measure_id' => (int) $request->validated(
                    'base_unit_of_measure_id',
                ),
                'inventory_category_id' => $request->validated(
                    'inventory_category_id',
                ),
                'inventory_brand_id' => $request->validated(
                    'inventory_brand_id',
                ),
                'model_number' => $request->validated('model_number'),
                'manufacturer_part_number' => $request->validated(
                    'manufacturer_part_number',
                ),
                'description' => $request->validated('description'),
                'type' => InventoryItemType::from((string) $request->validated(
                    'type',
                )),
                'yield_percentage' => (string) $request->validated(
                    'yield_percentage',
                ),
                'active' => (bool) $request->validated('active'),
            ],
            $item,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Inventory item updated.'),
        ]);

        return to_route(
            'inventory.items.edit',
            $inventoryItem,
        );
    }

    /**
     * Build active UOM choices for item forms.
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     symbol: string,
     *     active: bool
     * }>
     */
    private function activeUnitOptions(
        Organization $organization,
    ): array {
        $units = $organization
            ->unitsOfMeasure()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(
                static fn (UnitOfMeasure $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'active' => $unit->active,
                ],
            )
            ->all();

        return array_values($units);
    }

    /**
     * Build active category choices for item forms.
     *
     * @return list<array{id: int, name: string, active: bool}>
     */
    private function activeCategoryOptions(Organization $organization): array
    {
        $categories = $organization
            ->inventoryCategories()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(
                static fn (InventoryCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'active' => $category->active,
                ],
            )
            ->all();

        return array_values($categories);
    }

    /**
     * Include an item's assigned inactive category as a retainable choice.
     *
     * @return list<array{id: int, name: string, active: bool}>
     */
    private function categoryOptionsForItem(
        Organization $organization,
        InventoryItem $inventoryItem,
    ): array {
        $categories = $this->activeCategoryOptions($organization);
        $currentCategory = $inventoryItem->inventoryCategory;

        if ($currentCategory !== null && ! $currentCategory->active) {
            $categories[] = [
                'id' => $currentCategory->id,
                'name' => $currentCategory->name,
                'active' => $currentCategory->active,
            ];
        }

        return $categories;
    }

    /**
     * Build active brand choices for item forms.
     *
     * @return list<array{id: int, name: string, active: bool}>
     */
    private function activeBrandOptions(Organization $organization): array
    {
        $brands = $organization
            ->inventoryBrands()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(
                static fn (InventoryBrand $brand): array => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'active' => $brand->active,
                ],
            )
            ->all();

        return array_values($brands);
    }

    /**
     * Include an item's assigned inactive brand as a retainable choice.
     *
     * @return list<array{id: int, name: string, active: bool}>
     */
    private function brandOptionsForItem(
        Organization $organization,
        InventoryItem $inventoryItem,
    ): array {
        $brands = $this->activeBrandOptions($organization);
        $currentBrand = $inventoryItem->inventoryBrand;

        if ($currentBrand !== null && ! $currentBrand->active) {
            $brands[] = [
                'id' => $currentBrand->id,
                'name' => $currentBrand->name,
                'active' => $currentBrand->active,
            ];
        }

        return $brands;
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
