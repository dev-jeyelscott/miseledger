<?php

namespace App\Models;

use Database\Factories\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $symbol
 * @property string $dimension
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'name',
    'symbol',
    'dimension',
    'active',
])]
class UnitOfMeasure extends Model
{
    /** @use HasFactory<UnitOfMeasureFactory> */
    use HasFactory;

    protected $table = 'units_of_measure';

    /**
     * Get the organization owning this UOM.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get inventory items using this as their base UOM.
     *
     * @return HasMany<InventoryItem, $this>
     */
    public function baseInventoryItems(): HasMany
    {
        return $this->hasMany(
            InventoryItem::class,
            'base_unit_of_measure_id',
        );
    }

    /**
     * Get item-specific conversions using this UOM.
     *
     * @return HasMany<InventoryItemUnit, $this>
     */
    public function inventoryItemUnits(): HasMany
    {
        return $this->hasMany(InventoryItemUnit::class);
    }

    /**
     * Cast persisted UOM state.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
