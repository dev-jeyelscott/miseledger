<?php

namespace App\Http\Resources\Mobile;

use App\Models\StockBalance;
use App\Models\UnitOfMeasure;
use App\Support\Mobile\ScannedItemData;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property ScannedItemData $resource
 */
final class ScannedItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource->inventoryItem;
        $baseUnit = $item->baseUnitOfMeasure;
        $matchedItemUnit = $this->resource->matchedItemUnit;
        $matchedUnit = $matchedItemUnit !== null ? $matchedItemUnit->unitOfMeasure : $baseUnit;

        return [
            'inventoryItemId' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'matchedUnit' => $this->unitShape($matchedUnit, $baseUnit),
            'baseUnit' => $this->unitShape($baseUnit, $baseUnit),
            'stockAtActiveLocation' => [
                'quantityOnHand' => (string) $this->totalQuantity($this->resource->stockBalances),
                'unitSymbol' => $baseUnit->symbol,
                'byStorageLocation' => $this->resource->stockBalances
                    ->map(fn (StockBalance $balance): array => [
                        'storageLocationId' => $balance->storage_location_id,
                        'name' => $balance->storageLocation->name,
                        'quantityOnHand' => $balance->quantity_on_hand,
                    ])
                    ->values()
                    ->all(),
            ],
            'availableActions' => $this->resource->availableActions,
        ];
    }

    /**
     * @return array{id: int, name: string, symbol: string, isBase: bool}
     */
    private function unitShape(UnitOfMeasure $unit, UnitOfMeasure $baseUnit): array
    {
        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'symbol' => $unit->symbol,
            'isBase' => $unit->id === $baseUnit->id,
        ];
    }

    /**
     * @param  Collection<int, StockBalance>  $balances
     */
    private function totalQuantity(Collection $balances): BigDecimal
    {
        return $balances->reduce(
            static fn (BigDecimal $total, StockBalance $balance): BigDecimal => $total->plus(
                $balance->quantity_on_hand,
            ),
            BigDecimal::zero(),
        );
    }
}
