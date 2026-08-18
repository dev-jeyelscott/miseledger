<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StorageLocation;
use App\Support\Csv\CsvExport;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockOnHandReportController extends Controller
{
    /**
     * Report current balance quantities and values, scoped to the active
     * tenant, with cost fields hidden from members lacking cost visibility.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        [$filters, $query, $canViewCosts] = $this->filteredQuery(
            $request,
            $organization,
        );

        $balances = (clone $query)
            ->orderBy('location_id')
            ->orderBy('storage_location_id')
            ->get();

        $rows = $balances
            ->map(
                fn (StockBalance $balance): array => $this->rowData(
                    $balance,
                    $canViewCosts,
                ),
            )
            ->values()
            ->all();

        return Inertia::render('inventory/stock-on-hand', [
            'rows' => $rows,
            'summary' => $this->summaryData($balances, $canViewCosts),
            'locationOptions' => $this->locationOptions($organization),
            'storageLocationOptions' => $this->storageLocationOptions(
                $organization,
                $filters['locationId'],
            ),
            'categoryOptions' => $this->categoryOptions($organization),
            'filters' => $filters,
            'currency' => $organization->currency,
            'canViewCosts' => $canViewCosts,
        ]);
    }

    /**
     * Stream the same permission- and tenant-scoped rows as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

        [, $query, $canViewCosts] = $this->filteredQuery(
            $request,
            $organization,
        );

        $header = [
            'Location',
            'Storage Location',
            'Item',
            'SKU',
            'Category',
            'Quantity on Hand',
            'Unit',
            'Average Unit Cost',
            'Inventory Value',
        ];

        $rows = (function () use ($query, $canViewCosts): iterable {
            foreach (
                $query
                    ->orderBy('location_id')
                    ->orderBy('storage_location_id')
                    ->cursor() as $balance
            ) {
                $data = $this->rowData($balance, $canViewCosts);

                yield [
                    $data['locationName'],
                    $data['storageLocationName'],
                    $data['itemName'],
                    $data['itemSku'],
                    $data['categoryName'],
                    $data['quantityOnHand'],
                    $data['baseUnitSymbol'],
                    $data['averageUnitCost'],
                    $data['inventoryValue'],
                ];
            }
        })();

        return CsvExport::download(
            'stock-on-hand.csv',
            $header,
            $rows,
        );
    }

    /**
     * Build the shared tenant-scoped, filtered query behind every rendering
     * of the Stock on Hand report.
     *
     * @return array{0: array{locationId: int|null, storageLocationId: int|null, inventoryCategoryId: int|null, inventoryItemId: int|null, itemSearch: string|null}, 1: EloquentBuilder<StockBalance>, 2: bool}
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
            ? trim($validated['item'])
            : null;

        if ($itemSearch === '') {
            $itemSearch = null;
        }

        $query = StockBalance::query()
            ->with([
                'location:id,name',
                'storageLocation:id,name',
                'inventoryItem:id,name,sku,inventory_category_id,base_unit_of_measure_id',
                'inventoryItem.baseUnitOfMeasure:id,name,symbol',
                'inventoryItem.inventoryCategory:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->where('quantity_on_hand', '<>', '0');

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
                                $searchQuery->orWhereKey(
                                    (int) $itemSearch,
                                );
                            }
                        },
                    );
                },
            );
        }

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        return [
            [
                'locationId' => $locationId,
                'storageLocationId' => $storageLocationId,
                'inventoryCategoryId' => $categoryId,
                'inventoryItemId' => $itemId,
                'itemSearch' => $itemSearch,
            ],
            $query,
            $canViewCosts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowData(
        StockBalance $balance,
        bool $canViewCosts,
    ): array {
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
            'averageUnitCost' => $canViewCosts
                ? $balance->average_unit_cost
                : null,
            'inventoryValue' => $canViewCosts
                ? $balance->inventory_value
                : null,
        ];
    }

    /**
     * Build operational metrics from the same filtered balance collection used
     * by the report so card values and table rows cannot drift apart.
     *
     * @param  Collection<int, StockBalance>  $balances
     * @return array{itemsWithStockCount: int, storageLocationCount: int, totalValue: string|null}
     */
    private function summaryData(
        Collection $balances,
        bool $canViewCosts,
    ): array {
        return [
            'itemsWithStockCount' => $balances
                ->pluck('inventory_item_id')
                ->unique()
                ->count(),
            'storageLocationCount' => $balances
                ->pluck('storage_location_id')
                ->unique()
                ->count(),
            'totalValue' => $canViewCosts
                ? (string) $this->sumValues($balances)
                : null,
        ];
    }

    /**
     * Sum stock value without introducing floating-point arithmetic.
     *
     * @param  Collection<int, StockBalance>  $balances
     */
    private function sumValues(Collection $balances): BigDecimal
    {
        return $balances->reduce(
            static fn (
                BigDecimal $total,
                StockBalance $balance,
            ): BigDecimal => $total->plus(
                $balance->inventory_value,
            ),
            BigDecimal::zero(),
        );
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
