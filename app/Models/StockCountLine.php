<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $stock_count_id
 * @property int $inventory_item_id
 * @property string $expected_base_quantity
 * @property string $counted_quantity
 * @property int $count_unit_id
 * @property string $counted_base_quantity
 * @property string $variance_base_quantity
 * @property string $variance_unit_cost
 * @property string $variance_total_cost
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'stock_count_id',
    'inventory_item_id',
    'expected_base_quantity',
    'counted_quantity',
    'count_unit_id',
    'counted_base_quantity',
    'variance_base_quantity',
    'variance_unit_cost',
    'variance_total_cost',
    'notes',
])]
class StockCountLine extends Model
{
    /**
     * Get the stock count owning this evidence line.
     *
     * @return BelongsTo<StockCount, $this>
     */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    /**
     * Get the inventory item physically counted.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the practical unit entered during counting.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function countUnit(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'count_unit_id',
        );
    }

    /**
     * Get the non-zero variance movement created for this line.
     *
     * @return HasOne<StockMovement, $this>
     */
    public function movement(): HasOne
    {
        return $this
            ->hasOne(StockMovement::class, 'reference_id')
            ->where('reference_type', 'stock_count_line');
    }

    /**
     * Preserve quantity and cost values as fixed-precision strings.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_base_quantity' => 'decimal:6',
            'counted_quantity' => 'decimal:6',
            'counted_base_quantity' => 'decimal:6',
            'variance_base_quantity' => 'decimal:6',
            'variance_unit_cost' => 'decimal:4',
            'variance_total_cost' => 'decimal:4',
        ];
    }
}
