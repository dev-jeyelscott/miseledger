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
        if ($location->organization_id !== $organization->getKey()) {
            throw LocationItemCostQueryException::locationNotInOrganization(
                $location->id,
                $organization->id,
            );
        }

        if ($inventoryItem->organization_id !== $organization->getKey()) {
            throw LocationItemCostQueryException::inventoryItemNotInOrganization(
                $inventoryItem->id,
                $organization->id,
            );
        }

        $totals = StockBalance::query()
            ->where('organization_id', $organization->getKey())
            ->where('location_id', $location->getKey())
            ->where('inventory_item_id', $inventoryItem->getKey())
            ->selectRaw('coalesce(sum(quantity_on_hand), 0) as total_quantity, coalesce(sum(inventory_value), 0) as total_value')
            ->first();

        $totalQuantity = BigDecimal::of((string) $totals->total_quantity)
            ->toScale(self::QUANTITY_SCALE, RoundingMode::HalfUp);

        $totalValue = BigDecimal::of((string) $totals->total_value)
            ->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);

        if ($totalQuantity->isLessThanOrEqualTo(BigDecimal::zero())) {
            return new LocationItemCost(
                quantityOnHand: (string) $totalQuantity,
                inventoryValue: (string) $totalValue,
                averageUnitCost: (string) BigDecimal::zero()->toScale(self::MONEY_SCALE),
            );
        }

        $averageUnitCost = $totalValue->dividedBy(
            $totalQuantity,
            self::MONEY_SCALE,
            RoundingMode::HalfUp,
        );

        return new LocationItemCost(
            quantityOnHand: (string) $totalQuantity,
            inventoryValue: (string) $totalValue,
            averageUnitCost: (string) $averageUnitCost,
        );
    }
}
