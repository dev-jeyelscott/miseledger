<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\PurchaseOrderLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Dependencies validated and locked once for a batch stock operation.
 *
 * @phpstan-type ModelMap array<int, InventoryItem|PurchaseOrderLine|StorageLocation|UnitOfMeasure>
 */
final class LockedDependencies
{
    /** @var Collection<int, PurchaseOrderLine> */
    public readonly Collection $purchaseOrderLines;

    /** @var Collection<int, InventoryItem> */
    public readonly Collection $inventoryItems;

    /** @var Collection<int, UnitOfMeasure> */
    public readonly Collection $baseUnits;

    /** @var Collection<int, StorageLocation> */
    public readonly Collection $storageLocations;

    /** @var Collection<string, StockBalance> */
    public readonly Collection $stockBalances;

    /** @var Collection<string, StockMovement> */
    public readonly Collection $idempotentMovements;

    /**
     * @param  Collection<int, PurchaseOrderLine>  $purchaseOrderLines
     * @param  Collection<int, InventoryItem>  $inventoryItems
     * @param  Collection<int, UnitOfMeasure>  $baseUnits
     * @param  Collection<int, StorageLocation>  $storageLocations
     * @param  Collection<string, StockBalance>  $stockBalances
     * @param  Collection<string, StockMovement>  $idempotentMovements
     */
    public function __construct(
        public readonly Location $location,
        Collection $purchaseOrderLines,
        Collection $inventoryItems,
        Collection $baseUnits,
        Collection $storageLocations,
        public readonly bool $actorMembershipValidated = false,
        Collection $stockBalances = new Collection,
        Collection $idempotentMovements = new Collection,
    ) {
        $this->purchaseOrderLines = $purchaseOrderLines;
        $this->inventoryItems = $inventoryItems;
        $this->baseUnits = $baseUnits;
        $this->storageLocations = $storageLocations;
        $this->stockBalances = $stockBalances;
        $this->idempotentMovements = $idempotentMovements;
    }

    public function purchaseOrderLine(int $id): PurchaseOrderLine
    {
        return $this->get($this->purchaseOrderLines, $id, PurchaseOrderLine::class);
    }

    public function inventoryItem(int $id): InventoryItem
    {
        return $this->get($this->inventoryItems, $id, InventoryItem::class);
    }

    public function baseUnit(int $id): UnitOfMeasure
    {
        return $this->get($this->baseUnits, $id, UnitOfMeasure::class);
    }

    public function storageLocation(int $id): StorageLocation
    {
        return $this->get($this->storageLocations, $id, StorageLocation::class);
    }

    public function stockBalance(string $key): ?StockBalance
    {
        $balance = $this->stockBalances->get($key);

        return $balance instanceof StockBalance ? $balance : null;
    }

    public function idempotentMovement(string $key): ?StockMovement
    {
        $movement = $this->idempotentMovements->get($key);

        return $movement instanceof StockMovement ? $movement : null;
    }

    public function withStockState(
        /** @var Collection<string, StockBalance> $stockBalances */
        Collection $stockBalances,
        /** @var Collection<string, StockMovement> $idempotentMovements */
        Collection $idempotentMovements,
    ): self {
        return new self(
            location: $this->location,
            purchaseOrderLines: $this->purchaseOrderLines,
            inventoryItems: $this->inventoryItems,
            baseUnits: $this->baseUnits,
            storageLocations: $this->storageLocations,
            actorMembershipValidated: $this->actorMembershipValidated,
            stockBalances: $stockBalances,
            idempotentMovements: $idempotentMovements,
        );
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $models
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    private function get(Collection $models, int $id, string $modelClass): Model
    {
        $model = $models->get($id);

        if ($model instanceof $modelClass) {
            return $model;
        }

        throw (new ModelNotFoundException)->setModel($modelClass, [$id]);
    }
}
