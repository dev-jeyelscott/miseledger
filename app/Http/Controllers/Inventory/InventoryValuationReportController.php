<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
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

class InventoryValuationReportController extends Controller
{
    /**
     * Report current inventory value, scoped to the active tenant, with
     * cost fields hidden from members lacking cost visibility and totals
     * aggregated by location and category.
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

        $balances = $query
            ->orderBy('location_id')
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

        return Inertia::render('inventory/valuation', [
            'rows' => $rows,
            'locationTotals' => $canViewCosts
                ? $this->totalsByLocation($balances)
                : [],
            'categoryTotals' => $canViewCosts
                ? $this->totalsByCategory($balances)
                : [],
            'grandTotal' => $canViewCosts
                ? (string) $this->sumValues($balances)
                : null,
            'locationOptions' => $this->locationOptions($organization),
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
                    ->cursor() as $balance
            ) {
                $data = $this->rowData($balance, $canViewCosts);

                yield [
                    $data['locationName'],
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
            'inventory-valuation.csv',
            $header,
            $rows,
        );
    }

    /**
     * Build the shared tenant-scoped, filtered query behind every rendering
     * of the Inventory Valuation report.
     *
     * @return array{0: array{locationId: int|null, inventoryCategoryId: int|null}, 1: EloquentBuilder<StockBalance>, 2: bool}
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
        ]);

        $locationId = isset($validated['location_id'])
            ? (int) $validated['location_id']
            : null;

        $categoryId = isset($validated['inventory_category_id'])
            ? (int) $validated['inventory_category_id']
            : null;

        $query = StockBalance::query()
            ->with([
                'location:id,name',
                'inventoryItem:id,name,sku,inventory_category_id,base_unit_of_measure_id',
                'inventoryItem.baseUnitOfMeasure:id,symbol',
                'inventoryItem.inventoryCategory:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->where('quantity_on_hand', '<>', '0');

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
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

        $canViewCosts = Gate::allows(
            OrganizationPermission::CostsView->value,
            $organization,
        );

        return [
            [
                'locationId' => $locationId,
                'inventoryCategoryId' => $categoryId,
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
            'itemId' => $balance->inventory_item_id,
            'itemName' => $balance->inventoryItem->name,
            'itemSku' => $balance->inventoryItem->sku,
            'categoryId' => $balance->inventoryItem->inventory_category_id,
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
     * @param  Collection<int, StockBalance>  $balances
     * @return list<array{locationId: int, locationName: string, quantity: string, value: string}>
     */
    private function totalsByLocation(Collection $balances): array
    {
        return array_values(
            $balances
                ->groupBy('location_id')
                ->map(
                    fn (Collection $group): array => [
                        'locationId' => $group->first()->location_id,
                        'locationName' => $group->first()->location->name,
                        'quantity' => (string) $this->sumQuantities($group),
                        'value' => (string) $this->sumValues($group),
                    ],
                )
                ->all(),
        );
    }

    /**
     * @param  Collection<int, StockBalance>  $balances
     * @return list<array{categoryId: int|null, categoryName: string|null, quantity: string, value: string}>
     */
    private function totalsByCategory(Collection $balances): array
    {
        return array_values(
            $balances
                ->groupBy(
                    fn (StockBalance $balance): int|string => $balance->inventoryItem->inventory_category_id ?? 'uncategorized',
                )
                ->map(
                    fn (Collection $group): array => [
                        'categoryId' => $group->first()->inventoryItem->inventory_category_id,
                        'categoryName' => $group->first()->inventoryItem->inventoryCategory?->name,
                        'quantity' => (string) $this->sumQuantities($group),
                        'value' => (string) $this->sumValues($group),
                    ],
                )
                ->all(),
        );
    }

    /**
     * @param  Collection<int, StockBalance>  $balances
     */
    private function sumQuantities(Collection $balances): BigDecimal
    {
        return $balances->reduce(
            static fn (BigDecimal $total, StockBalance $balance): BigDecimal => $total->plus(
                $balance->quantity_on_hand,
            ),
            BigDecimal::zero(),
        );
    }

    /**
     * @param  Collection<int, StockBalance>  $balances
     */
    private function sumValues(Collection $balances): BigDecimal
    {
        return $balances->reduce(
            static fn (BigDecimal $total, StockBalance $balance): BigDecimal => $total->plus(
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
