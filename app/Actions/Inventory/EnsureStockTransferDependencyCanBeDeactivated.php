<?php

namespace App\Actions\Inventory;

use App\Enums\StockTransferStatus;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StorageLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class EnsureStockTransferDependencyCanBeDeactivated
{
    /**
     * Prevent deactivating a destination location required by in-transit stock.
     */
    public function assertLocationCanBeDeactivated(
        Organization $organization,
        Location $location,
    ): void {
        $this->assertNoShippedTransfer(
            StockTransfer::query()
                ->where(
                    'organization_id',
                    $organization->getKey(),
                )
                ->where(
                    'to_location_id',
                    $location->getKey(),
                ),
            __(
                'This location cannot be deactivated while a shipped stock transfer is awaiting receipt here.',
            ),
        );
    }

    /**
     * Prevent deactivating destination storage required by in-transit stock.
     */
    public function assertStorageLocationCanBeDeactivated(
        Organization $organization,
        StorageLocation $storageLocation,
    ): void {
        $this->assertNoShippedTransfer(
            StockTransfer::query()
                ->where(
                    'organization_id',
                    $organization->getKey(),
                )
                ->where(
                    'to_storage_location_id',
                    $storageLocation->getKey(),
                ),
            __(
                'This storage location cannot be deactivated while a shipped stock transfer is awaiting receipt here.',
            ),
        );
    }

    /**
     * Prevent deactivating an inventory item required by in-transit stock.
     */
    public function assertInventoryItemCanBeDeactivated(
        Organization $organization,
        InventoryItem $inventoryItem,
    ): void {
        $this->assertNoShippedTransfer(
            StockTransfer::query()
                ->where(
                    'organization_id',
                    $organization->getKey(),
                )
                ->whereIn(
                    'id',
                    StockTransferLine::query()
                        ->select('stock_transfer_id')
                        ->where(
                            'inventory_item_id',
                            $inventoryItem->getKey(),
                        ),
                ),
            __(
                'This inventory item cannot be deactivated while it is included in a shipped stock transfer awaiting receipt.',
            ),
        );
    }

    /**
     * Lock potentially shippable transfers and reject any already in transit.
     *
     * Draft rows are also locked so deactivation cannot race a shipment that
     * is about to transition the transfer to shipped.
     *
     * @param  Builder<StockTransfer>  $query
     */
    private function assertNoShippedTransfer(
        Builder $query,
        string $message,
    ): void {
        $transfers = $query
            ->whereIn('status', [
                StockTransferStatus::Draft->value,
                StockTransferStatus::Shipped->value,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get([
                'id',
                'status',
            ]);

        $hasShippedTransfer = $transfers->contains(
            static fn (StockTransfer $transfer): bool => (
                $transfer->status === StockTransferStatus::Shipped
            ),
        );

        if (! $hasShippedTransfer) {
            return;
        }

        throw ValidationException::withMessages([
            'active' => $message,
        ]);
    }
}
