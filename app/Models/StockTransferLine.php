<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $stock_transfer_id
 * @property int $inventory_item_id
 * @property string $requested_quantity
 * @property int $unit_id
 * @property string $requested_base_quantity
 * @property string|null $shipped_base_quantity
 * @property string|null $received_base_quantity
 * @property string|null $unit_cost
 * @property string|null $variance_base_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'stock_transfer_id',
    'inventory_item_id',
    'requested_quantity',
    'unit_id',
    'requested_base_quantity',
    'shipped_base_quantity',
    'received_base_quantity',
    'unit_cost',
    'variance_base_quantity',
])]
class StockTransferLine extends Model
{
    /**
     * Get the owning stock transfer.
     *
     * @return BelongsTo<StockTransfer, $this>
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * Get the transferred inventory item.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the practical requested unit.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'unit_id',
        );
    }

    /**
     * Get the authoritative outbound transfer movement.
     *
     * @return HasOne<StockMovement, $this>
     */
    public function outboundMovement(): HasOne
    {
        return $this
            ->hasOne(StockMovement::class, 'reference_id')
            ->where(
                'reference_type',
                'stock_transfer_line',
            )
            ->where(
                'type',
                StockMovementType::TransferOut->value,
            );
    }

    /**
     * Get the authoritative inbound transfer movement.
     *
     * @return HasOne<StockMovement, $this>
     */
    public function inboundMovement(): HasOne
    {
        return $this
            ->hasOne(StockMovement::class, 'reference_id')
            ->where(
                'reference_type',
                'stock_transfer_line',
            )
            ->where(
                'type',
                StockMovementType::TransferIn->value,
            );
    }

    /**
     * Preserve quantities and cost as fixed-precision strings.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:6',
            'requested_base_quantity' => 'decimal:6',
            'shipped_base_quantity' => 'decimal:6',
            'received_base_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'variance_base_quantity' => 'decimal:6',
        ];
    }
}
