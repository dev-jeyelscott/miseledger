<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $location_id
 * @property int $storage_location_id
 * @property int $inventory_item_id
 * @property int $waste_reason_id
 * @property string $operation_id
 * @property string $quantity
 * @property int $unit_id
 * @property string $base_quantity
 * @property string $unit_cost
 * @property string $total_cost
 * @property Carbon $occurred_at
 * @property int|null $recorded_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'organization_id',
    'location_id',
    'storage_location_id',
    'inventory_item_id',
    'waste_reason_id',
    'operation_id',
    'quantity',
    'unit_id',
    'base_quantity',
    'unit_cost',
    'total_cost',
    'occurred_at',
    'recorded_by',
    'notes',
])]
class WasteRecord extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the owning organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the restaurant location where waste occurred.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the physical storage location where stock was removed.
     *
     * @return BelongsTo<StorageLocation, $this>
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get the wasted inventory item.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the retained business reason.
     *
     * @return BelongsTo<WasteReason, $this>
     */
    public function wasteReason(): BelongsTo
    {
        return $this->belongsTo(WasteReason::class);
    }

    /**
     * Get the unit entered by the operator.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    /**
     * Get the operator who recorded the waste.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the authoritative WASTE ledger movement.
     *
     * @return HasOne<StockMovement, $this>
     */
    public function movement(): HasOne
    {
        return $this->hasOne(
            StockMovement::class,
            'reference_id',
        )->where(
            'reference_type',
            'waste_record',
        );
    }

    /**
     * Cast immutable quantity, cost, and timestamp evidence.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'base_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }
}
