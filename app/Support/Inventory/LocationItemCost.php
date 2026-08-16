<?php

namespace App\Support\Inventory;

final readonly class LocationItemCost
{
    /**
     * Carry the aggregated cost result at authoritative decimal precision.
     */
    public function __construct(
        public string $quantityOnHand,
        public string $inventoryValue,
        public string $averageUnitCost,
    ) {}
}
