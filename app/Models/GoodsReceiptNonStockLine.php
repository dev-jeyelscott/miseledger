<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $goods_receipt_id
 * @property int|null $goods_receipt_line_id
 * @property int $purchase_order_line_id
 * @property int $inventory_item_id
 * @property string|null $rejected_quantity
 * @property int|null $rejected_unit_of_measure_id
 * @property string|null $rejected_base_quantity
 * @property string|null $damaged_quantity
 * @property int|null $damaged_unit_of_measure_id
 * @property string|null $damaged_base_quantity
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'goods_receipt_id',
    'goods_receipt_line_id',
    'purchase_order_line_id',
    'inventory_item_id',
    'rejected_quantity',
    'rejected_unit_of_measure_id',
    'rejected_base_quantity',
    'damaged_quantity',
    'damaged_unit_of_measure_id',
    'damaged_base_quantity',
    'notes',
])]
class GoodsReceiptNonStockLine extends Model
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
     * Get the accepted stock-bearing line when this evidence accompanied one.
     *
     * @return BelongsTo<GoodsReceiptLine, $this>
     */
    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }

    /**
     * Get the originating purchase-order line.
     *
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /**
     * Get the inventory item documented by this evidence.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the UOM entered for rejected quantity.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function rejectedUnitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'rejected_unit_of_measure_id',
        );
    }

    /**
     * Get the UOM entered for damaged quantity.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function damagedUnitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'damaged_unit_of_measure_id',
        );
    }

    /**
     * Cast immutable non-stock quantity snapshots without floating point.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rejected_quantity' => 'decimal:6',
            'rejected_base_quantity' => 'decimal:6',
            'damaged_quantity' => 'decimal:6',
            'damaged_base_quantity' => 'decimal:6',
        ];
    }
}
