<?php

namespace App\Support\Inventory;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class LocationItemCostQuery
{
    private const QUANTITY_SCALE = 6;

    private const MONEY_SCALE = 4;

    /**
     * Resolve the current average cost of an item across every storage
     * location within one restaurant location, deterministically.
     *
     * The approved cost rule is: total inventory value across storage
     * locations divided by total positive quantity on hand. When the total
     * quantity on hand is zero or negative, the average cost is explicitly
     * reported as zero rather than dividing by zero.
     */
    public static function resolve(
        Organization $organization,
        Location $location,
        InventoryItem $inventoryItem,
    ): LocationItemCost {
        return self::resolveMany($organization, $location, [
            $inventoryItem->getKey() => $inventoryItem,
        ])[$inventoryItem->getKey()];
    }

    /**
     * Resolve the current average cost of many inventory items at one
     * location in a single grouped aggregate query, rather than one query
     * per item. Each item is validated against the organization the same
     * way the single-item lookup is.
     *
     * @param  array<int, InventoryItem>  $inventoryItemsById
     * @return array<int, LocationItemCost>
     */
    public static function resolveMany(
        Organization $organization,
        Location $location,
        array $inventoryItemsById,
    ): array {
        if ($location->organization_id !== $organization->getKey()) {
            throw LocationItemCostQueryException::locationNotInOrganization(
                $location->id,
                $organization->id,
            );
        }

        foreach ($inventoryItemsById as $inventoryItem) {
            if ($inventoryItem->organization_id !== $organization->getKey()) {
                throw LocationItemCostQueryException::inventoryItemNotInOrganization(
                    $inventoryItem->id,
                    $organization->id,
                );
            }
        }

        if ($inventoryItemsById === []) {
            return [];
        }

        $totalsByItemId = StockBalance::query()
            ->where('organization_id', $organization->getKey())
            ->where('location_id', $location->getKey())
            ->whereIn('inventory_item_id', array_keys($inventoryItemsById))
            ->toBase()
            ->selectRaw('inventory_item_id, coalesce(sum(quantity_on_hand), 0) as total_quantity, coalesce(sum(inventory_value), 0) as total_value')
            ->groupBy('inventory_item_id')
            ->get()
            ->keyBy('inventory_item_id');

        $results = [];

        foreach ($inventoryItemsById as $itemId => $inventoryItem) {
            $totals = $totalsByItemId->get($itemId);

            $totalQuantity = BigDecimal::of((string) ($totals->total_quantity ?? 0))
                ->toScale(self::QUANTITY_SCALE, RoundingMode::HalfUp);

            $totalValue = BigDecimal::of((string) ($totals->total_value ?? 0))
                ->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);

            if ($totalQuantity->isLessThanOrEqualTo(BigDecimal::zero())) {
                $results[$itemId] = new LocationItemCost(
                    quantityOnHand: (string) $totalQuantity,
                    inventoryValue: (string) $totalValue,
                    averageUnitCost: (string) BigDecimal::zero()->toScale(self::MONEY_SCALE),
                );

                continue;
            }

            $averageUnitCost = $totalValue->dividedBy(
                $totalQuantity,
                self::MONEY_SCALE,
                RoundingMode::HalfUp,
            );

            $results[$itemId] = new LocationItemCost(
                quantityOnHand: (string) $totalQuantity,
                inventoryValue: (string) $totalValue,
                averageUnitCost: (string) $averageUnitCost,
            );
        }

        return $results;
    }
}
