<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $goods_receipt_id
 * @property int $purchase_order_line_id
 * @property int $inventory_item_id
 * @property int $storage_location_id
 * @property string $received_quantity
 * @property int $received_unit_of_measure_id
 * @property string $base_quantity
 * @property string $unit_cost
 * @property string $total_cost
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'goods_receipt_id',
    'purchase_order_line_id',
    'inventory_item_id',
    'storage_location_id',
    'received_quantity',
    'received_unit_of_measure_id',
    'base_quantity',
    'unit_cost',
    'total_cost',
    'notes',
])]
class GoodsReceiptLine extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the owning goods receipt.
     *
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * Get the originating PO line.
     *
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /**
     * Get the inventory item being received.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the physical storage destination.
     *
     * @return BelongsTo<StorageLocation, $this>
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get the practical UOM entered during receiving.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function receivedUnitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'received_unit_of_measure_id',
        );
    }

    /**
     * Get the immutable ledger movement created for this receipt line.
     *
     * @return HasOne<StockMovement, $this>
     */
    public function movement(): HasOne
    {
        return $this
            ->hasOne(StockMovement::class, 'reference_id')
            ->where('reference_type', 'goods_receipt_line');
    }

    /**
     * Cast quantity and cost snapshots without floating point.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:6',
            'base_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }
}
