<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StorageLocation;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LowStockReportController extends Controller
{
    /**
     * Report balances at zero or negative quantity for the active organization.
     *
     * Reorder thresholds are intentionally not applied because MiseLedger has
     * no approved minimum-stock or PAR-level configuration.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        [$filters, $query] = $this->filteredQuery(
            $request,
            $organization,
        );

        $summary = $this->summaryData($query);

        $paginatedBalances = (clone $query)
            ->orderBy('location_id')
            ->orderBy('storage_location_id')
            ->orderBy('quantity_on_hand')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(
                fn (StockBalance $balance): array => $this->rowData($balance),
            );

        return Inertia::render('inventory/low-stock', [
            'rows' => $paginatedBalances->items(),
            'pagination' => [
                'current_page' => $paginatedBalances->currentPage(),
                'from' => $paginatedBalances->firstItem(),
                'last_page' => $paginatedBalances->lastPage(),
                'next_page_url' => $paginatedBalances->nextPageUrl(),
                'per_page' => $paginatedBalances->perPage(),
                'prev_page_url' => $paginatedBalances->previousPageUrl(),
                'to' => $paginatedBalances->lastItem(),
                'total' => $paginatedBalances->total(),
            ],
            'summary' => $summary,
            'locationOptions' => $this->locationOptions($organization),
            'storageLocationOptions' => $this->storageLocationOptions(
                $organization,
                $filters['locationId'],
            ),
            'categoryOptions' => $this->categoryOptions($organization),
            'filters' => $filters,
            'canManage' => Gate::allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            ),
        ]);
    }

    /**
     * Build the tenant-scoped filtered query shared by the report rows,
     * pagination, and summary metrics.
     *
     * @return array{
     *     0: array{
     *         locationId: int|null,
     *         storageLocationId: int|null,
     *         inventoryCategoryId: int|null,
     *         inventoryItemId: int|null,
     *         itemSearch: string|null,
     *         status: 'out_of_stock'|'negative'|null
     *     },
     *     1: EloquentBuilder<StockBalance>
     * }
     */
    private function filteredQuery(
        Request $request,
        Organization $organization,
    ): array {
        $validated = $request->validate([
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                ),
            ],
            'storage_location_id' => [
                'nullable',
                'integer',
                Rule::exists('storage_locations', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                ),
            ],
            'inventory_category_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_categories', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                ),
            ],
            'inventory_item_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_items', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organization->id,
                    ),
                ),
            ],
            'item' => [
                'nullable',
                'string',
                'max:120',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    'out_of_stock',
                    'negative',
                ]),
            ],
        ]);

        $locationId = isset($validated['location_id'])
            ? (int) $validated['location_id']
            : null;

        $storageLocationId = isset($validated['storage_location_id'])
            ? (int) $validated['storage_location_id']
            : null;

        $categoryId = isset($validated['inventory_category_id'])
            ? (int) $validated['inventory_category_id']
            : null;

        $itemId = isset($validated['inventory_item_id'])
            ? (int) $validated['inventory_item_id']
            : null;

        $itemSearch = isset($validated['item'])
            ? trim((string) $validated['item'])
            : null;

        if ($itemSearch === '') {
            $itemSearch = null;
        }

        $status = match ($validated['status'] ?? null) {
            'out_of_stock' => 'out_of_stock',
            'negative' => 'negative',
            default => null,
        };

        $query = StockBalance::query()
            ->with([
                'location:id,name',
                'storageLocation:id,name',
                'inventoryItem:id,name,sku,inventory_category_id,base_unit_of_measure_id',
                'inventoryItem.baseUnitOfMeasure:id,name,symbol',
                'inventoryItem.inventoryCategory:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->where('quantity_on_hand', '<=', '0');

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        if ($storageLocationId !== null) {
            $query->where('storage_location_id', $storageLocationId);
        }

        if ($categoryId !== null) {
            $query->whereHas(
                'inventoryItem',
                fn (EloquentBuilder $itemQuery): EloquentBuilder => $itemQuery->where(
                    'inventory_category_id',
                    $categoryId,
                ),
            );
        }

        if ($itemId !== null) {
            $query->where('inventory_item_id', $itemId);
        }

        if ($itemSearch !== null) {
            $query->whereHas(
                'inventoryItem',
                function (EloquentBuilder $itemQuery) use ($itemSearch): void {
                    $itemQuery->where(
                        function (
                            EloquentBuilder $searchQuery
                        ) use ($itemSearch): void {
                            $searchQuery
                                ->whereLike(
                                    'name',
                                    "%{$itemSearch}%",
                                )
                                ->orWhereLike(
                                    'sku',
                                    "%{$itemSearch}%",
                                );

                            if (ctype_digit($itemSearch)) {
                                $searchQuery->orWhere(
                                    'id',
                                    (int) $itemSearch,
                                );
                            }
                        },
                    );
                },
            );
        }

        if ($status === 'out_of_stock') {
            $query->where('quantity_on_hand', '=', '0');
        }

        if ($status === 'negative') {
            $query->where('quantity_on_hand', '<', '0');
        }

        return [
            [
                'locationId' => $locationId,
                'storageLocationId' => $storageLocationId,
                'inventoryCategoryId' => $categoryId,
                'inventoryItemId' => $itemId,
                'itemSearch' => $itemSearch,
                'status' => $status,
            ],
            $query,
        ];
    }

    /**
     * Transform one persisted balance without converting decimal quantities
     * to floating-point values.
     *
     * @return array{
     *     id: int,
     *     locationId: int,
     *     locationName: string,
     *     storageLocationId: int,
     *     storageLocationName: string,
     *     itemId: int,
     *     itemName: string,
     *     itemSku: string,
     *     categoryName: string|null,
     *     quantityOnHand: string,
     *     baseUnitSymbol: string,
     *     status: 'out_of_stock'|'negative'
     * }
     */
    private function rowData(StockBalance $balance): array
    {
        $status = BigDecimal::of($balance->quantity_on_hand)->isZero()
            ? 'out_of_stock'
            : 'negative';

        return [
            'id' => $balance->id,
            'locationId' => $balance->location_id,
            'locationName' => $balance->location->name,
            'storageLocationId' => $balance->storage_location_id,
            'storageLocationName' => $balance->storageLocation->name,
            'itemId' => $balance->inventory_item_id,
            'itemName' => $balance->inventoryItem->name,
            'itemSku' => $balance->inventoryItem->sku,
            'categoryName' => $balance
                ->inventoryItem
                ->inventoryCategory
                ?->name,
            'quantityOnHand' => $balance->quantity_on_hand,
            'baseUnitSymbol' => $balance
                ->inventoryItem
                ->baseUnitOfMeasure
                ->symbol,
            'status' => $status,
        ];
    }

    /**
     * Build truthful operational metrics from the same filtered low-stock
     * query used for the table.
     *
     * @param  EloquentBuilder<StockBalance>  $query
     * @return array{
     *     affectedBalanceCount: int,
     *     outOfStockCount: int,
     *     negativeCount: int,
     *     affectedLocationCount: int
     * }
     */
    private function summaryData(EloquentBuilder $query): array
    {
        return [
            'affectedBalanceCount' => (clone $query)->count(),
            'outOfStockCount' => (clone $query)
                ->where('quantity_on_hand', '=', '0')
                ->count(),
            'negativeCount' => (clone $query)
                ->where('quantity_on_hand', '<', '0')
                ->count(),
            'affectedLocationCount' => (clone $query)
                ->select('location_id')
                ->distinct()
                ->count('location_id'),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function locationOptions(Organization $organization): array
    {
        return array_values(
            Location::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(
                    static fn (Location $location): array => [
                        'id' => $location->id,
                        'name' => $location->name,
                    ],
                )
                ->all(),
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function storageLocationOptions(
        Organization $organization,
        ?int $locationId,
    ): array {
        $query = StorageLocation::query()
            ->where('organization_id', $organization->id);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return array_values(
            $query
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(
                    static fn (StorageLocation $storageLocation): array => [
                        'id' => $storageLocation->id,
                        'name' => $storageLocation->name,
                    ],
                )
                ->all(),
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function categoryOptions(Organization $organization): array
    {
        return array_values(
            InventoryCategory::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(
                    static fn (InventoryCategory $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                    ],
                )
                ->all(),
        );
    }

    /**
     * Resolve the middleware-provided active tenant or reject the request.
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
