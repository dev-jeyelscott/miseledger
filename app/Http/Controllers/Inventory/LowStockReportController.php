<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StorageLocation;
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
     * Report balances at zero or negative quantity, scoped to the active
     * tenant and location. No replenishment thresholds are applied because
     * this application has no approved minimum-stock configuration.
     */
    public function index(Request $request): Response
    {
        $organization = $this->activeOrganization($request);

        Gate::authorize(
            OrganizationPermission::ReportsView->value,
            $organization,
        );

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

        $rows = $query
            ->orderBy('location_id')
            ->orderBy('storage_location_id')
            ->orderBy('quantity_on_hand')
            ->get()
            ->map(
                static fn (StockBalance $balance): array => [
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
                ],
            )
            ->values()
            ->all();

        return Inertia::render('inventory/low-stock', [
            'rows' => $rows,
            'locationOptions' => $this->locationOptions($organization),
            'storageLocationOptions' => $this->storageLocationOptions(
                $organization,
                $locationId,
            ),
            'categoryOptions' => $this->categoryOptions($organization),
            'filters' => [
                'locationId' => $locationId,
                'storageLocationId' => $storageLocationId,
                'inventoryCategoryId' => $categoryId,
                'inventoryItemId' => $itemId,
            ],
        ]);
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
