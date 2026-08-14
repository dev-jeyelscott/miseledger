<?php

namespace App\Models;

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
 * @property string $quantity_on_hand
 * @property string $average_unit_cost
 * @property string $inventory_value
 * @property CarbonImmutable|null $last_movement_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'organization_id',
    'location_id',
    'storage_location_id',
    'inventory_item_id',
])]
class StockBalance extends Model
{
    /**
     * Mirror database defaults for newly constructed projection rows.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'quantity_on_hand' => '0.000000',
        'average_unit_cost' => '0.0000',
        'inventory_value' => '0.0000',
    ];

    /**
     * Get the organization owning this projection row.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the restaurant location represented by this balance.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the physical storage location represented by this balance.
     *
     * @return BelongsTo<StorageLocation, $this>
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get the inventory item represented by this balance.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Cast projection values without floating point conversion.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:6',
            'average_unit_cost' => 'decimal:4',
            'inventory_value' => 'decimal:4',
            'last_movement_at' => 'immutable_datetime',
        ];
    }

    /**
     * Keep balances writable only through their dedicated projection writers.
     */
    protected static function booted(): void
    {
        static::updating(static function (StockBalance $balance): never {
            throw new LogicException(
                'Stock balances are rebuilt projections and cannot be edited directly.',
            );
        });

        static::deleting(static function (StockBalance $balance): never {
            throw new LogicException(
                'Stock balances are rebuilt projections and cannot be deleted directly.',
            );
        });
    }
}
