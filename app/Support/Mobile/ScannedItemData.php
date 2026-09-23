<?php

namespace App\Support\Mobile;

use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\StockBalance;
use Illuminate\Support\Collection;

/**
 * The single read-model shape produced for every scan/search match, so the
 * camera path and the manual-search path never drift into two item shapes.
 */
final readonly class ScannedItemData
{
    /**
     * @param  Collection<int, StockBalance>  $stockBalances  balances for this item at the active location, one row per storage location
     * @param  list<string>  $availableActions
     */
    public function __construct(
        public InventoryItem $inventoryItem,
        public ?InventoryItemUnit $matchedItemUnit,
        public Collection $stockBalances,
        public array $availableActions,
    ) {}
}
