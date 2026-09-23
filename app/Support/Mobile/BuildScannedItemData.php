<?php

namespace App\Support\Mobile;

use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockBalance;
use Illuminate\Support\Collection;

/**
 * Batch-loads stock balances for a set of matched items at the active
 * location so scan/search results never issue one query per row.
 */
final class BuildScannedItemData
{
    /**
     * @param  Collection<int, InventoryItem>  $items
     * @param  array<int, InventoryItemUnit|null>  $matchedUnitsByItemId  keyed by inventory_item_id
     * @param  list<string>  $availableActions
     * @return Collection<int, ScannedItemData>
     */
    public function handle(
        Organization $organization,
        Location $location,
        Collection $items,
        array $matchedUnitsByItemId,
        array $availableActions,
    ): Collection {
        $itemIds = $items->pluck('id')->all();

        $balancesByItem = StockBalance::query()
            ->where('organization_id', $organization->getKey())
            ->where('location_id', $location->getKey())
            ->whereIn('inventory_item_id', $itemIds)
            ->with('storageLocation')
            ->get()
            ->groupBy('inventory_item_id');

        return $items
            ->map(fn (InventoryItem $item): ScannedItemData => new ScannedItemData(
                inventoryItem: $item,
                matchedItemUnit: $matchedUnitsByItemId[$item->id] ?? null,
                stockBalances: $balancesByItem->get($item->id, collect()),
                availableActions: $availableActions,
            ))
            ->values();
    }
}
