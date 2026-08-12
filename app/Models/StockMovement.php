<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $location_id
 * @property int $storage_location_id
 * @property int $inventory_item_id
 * @property StockMovementType $type
 * @property string $quantity
 * @property int $base_unit_of_measure_id
 * @property string|null $unit_cost
 * @property string|null $total_cost
 * @property string $reference_type
 * @property int $reference_id
 * @property CarbonImmutable $occurred_at
 * @property int|null $created_by
 * @property string|null $idempotency_key
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 */
#[Fillable([
    'organization_id',
    'location_id',
    'storage_location_id',
    'inventory_item_id',
    'type',
    'quantity',
    'base_unit_of_measure_id',
    'unit_cost',
    'total_cost',
    'reference_type',
    'reference_id',
    'occurred_at',
    'created_by',
    'idempotency_key',
    'notes',
])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the organization owning this movement.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the restaurant location affected by this movement.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the physical storage location affected by this movement.
     *
     * @return BelongsTo<StorageLocation, $this>
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get the inventory item affected by this movement.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the snapshotted base UOM used by the ledger movement.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function baseUnitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'base_unit_of_measure_id',
        );
    }

    /**
     * Get the user responsible for this movement, when applicable.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cast authoritative ledger values without floating point conversion.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Prevent generic mutation or deletion of committed ledger history.
     */
    protected static function booted(): void
    {
        static::updating(static function (StockMovement $movement): never {
            throw new LogicException(
                'Committed stock movements are immutable.',
            );
        });

        static::deleting(static function (StockMovement $movement): never {
            throw new LogicException(
                'Committed stock movements cannot be deleted.',
            );
        });
    }
}
