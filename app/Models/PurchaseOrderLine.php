<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $supplier_item_id
 * @property int $inventory_item_id
 * @property string $item_name_snapshot
 * @property string $supplier_sku_snapshot
 * @property string $ordered_quantity
 * @property int $purchase_unit_of_measure_id
 * @property string $base_quantity
 * @property string $unit_price
 * @property string $line_total
 * @property string $received_base_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'purchase_order_id',
    'supplier_item_id',
    'inventory_item_id',
    'item_name_snapshot',
    'supplier_sku_snapshot',
    'ordered_quantity',
    'purchase_unit_of_measure_id',
    'base_quantity',
    'unit_price',
    'line_total',
    'received_base_quantity',
])]
class PurchaseOrderLine extends Model
{
    /**
     * Get the owning purchase order.
     *
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the supplier-item mapping used when this line was created.
     *
     * @return BelongsTo<SupplierItem, $this>
     */
    public function supplierItem(): BelongsTo
    {
        return $this->belongsTo(SupplierItem::class);
    }

    /**
     * Get the internal inventory item.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the snapshotted purchase UOM.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function purchaseUnitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'purchase_unit_of_measure_id',
        );
    }

    /**
     * Get goods receipt lines posted against this PO line.
     *
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function goodsReceiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    /**
     * Cast authoritative quantity and price snapshots.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:6',
            'base_quantity' => 'decimal:6',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:2',
            'received_base_quantity' => 'decimal:6',
        ];
    }
}
