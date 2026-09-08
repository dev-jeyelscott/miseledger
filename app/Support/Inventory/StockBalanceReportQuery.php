<?php

namespace App\Support\Inventory;

use App\Models\Organization;
use App\Models\StockBalance;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bounded, organization-scoped balance reads shared by inventory reports and
 * read-only organization-data tools.
 */
final class StockBalanceReportQuery
{
    /**
     * Build the neutral organization-scoped stock-balance query.
     *
     * Unlike stockOnHand and lowStock, this deliberately applies no quantity
     * status predicate so callers can safely select positive, zero, or
     * negative balances themselves.
     *
     * @return Builder<StockBalance>
     */
    public function allBalances(
        Organization $organization,
        ?int $locationId,
        ?int $storageLocationId,
        ?int $categoryId,
        ?int $itemId,
        ?string $itemSearch,
    ): Builder {
        return $this->balances(
            $organization,
            $locationId,
            $storageLocationId,
            $categoryId,
            $itemId,
            $itemSearch,
        );
    }

    /**
     * Build the current non-zero stock-on-hand query.
     *
     * @return Builder<StockBalance>
     */
    public function stockOnHand(
        Organization $organization,
        ?int $locationId,
        ?int $storageLocationId,
        ?int $categoryId,
        ?int $itemId,
        ?string $itemSearch,
    ): Builder {
        return $this->allBalances(
            $organization,
            $locationId,
            $storageLocationId,
            $categoryId,
            $itemId,
            $itemSearch,
        )->where('quantity_on_hand', '<>', '0');
    }

    /**
     * Build the zero or negative stock query used by low-stock reporting.
     *
     * @param  'out_of_stock'|'negative'|null  $status
     * @return Builder<StockBalance>
     */
    public function lowStock(
        Organization $organization,
        ?int $locationId,
        ?int $storageLocationId,
        ?int $categoryId,
        ?int $itemId,
        ?string $itemSearch,
        ?string $status,
    ): Builder {
        $query = $this->allBalances(
            $organization,
            $locationId,
            $storageLocationId,
            $categoryId,
            $itemId,
            $itemSearch,
        )->where('quantity_on_hand', '<=', '0');

        if ($status === 'out_of_stock') {
            $query->where('quantity_on_hand', '=', '0');
        }

        if ($status === 'negative') {
            $query->where('quantity_on_hand', '<', '0');
        }

        return $query;
    }

    /**
     * Build the non-zero inventory valuation query.
     *
     * @return Builder<StockBalance>
     */
    public function valuation(
        Organization $organization,
        ?int $locationId,
        ?int $categoryId,
    ): Builder {
        return StockBalance::query()
            ->with([
                'location:id,name',
                'inventoryItem:id,name,sku,inventory_category_id,base_unit_of_measure_id',
                'inventoryItem.baseUnitOfMeasure:id,symbol',
                'inventoryItem.inventoryCategory:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->where('quantity_on_hand', '<>', '0')
            ->when(
                $locationId !== null,
                fn (Builder $query): Builder => $query->where(
                    'location_id',
                    $locationId,
                ),
            )
            ->when(
                $categoryId !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'inventoryItem',
                    fn (Builder $itemQuery): Builder => $itemQuery->where(
                        'inventory_category_id',
                        $categoryId,
                    ),
                ),
            );
    }

    /**
     * Build the authoritative organization-scoped StockBalance projection read.
     *
     * @return Builder<StockBalance>
     */
    private function balances(
        Organization $organization,
        ?int $locationId,
        ?int $storageLocationId,
        ?int $categoryId,
        ?int $itemId,
        ?string $itemSearch,
    ): Builder {
        $query = StockBalance::query()
            ->with([
                'location:id,name',
                'storageLocation:id,name',
                'inventoryItem:id,name,sku,inventory_category_id,base_unit_of_measure_id',
                'inventoryItem.baseUnitOfMeasure:id,name,symbol',
                'inventoryItem.inventoryCategory:id,name',
            ])
            ->where('organization_id', $organization->id);

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        if ($storageLocationId !== null) {
            $query->where('storage_location_id', $storageLocationId);
        }

        if ($categoryId !== null) {
            $query->whereHas(
                'inventoryItem',
                fn (Builder $itemQuery): Builder => $itemQuery->where(
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
                function (Builder $itemQuery) use ($itemSearch): void {
                    $itemQuery->where(
                        function (Builder $searchQuery) use ($itemSearch): void {
                            $searchQuery
                                ->whereLike('name', "%{$itemSearch}%")
                                ->orWhereLike('sku', "%{$itemSearch}%");

                            if (ctype_digit($itemSearch)) {
                                $searchQuery->orWhere('id', (int) $itemSearch);
                            }
                        },
                    );
                },
            );
        }

        return $query;
    }
}
