<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $base_unit_of_measure_id
 * @property string $name
 * @property string $sku
 * @property bool $active
 * @property int|null $unit_conversions_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UnitOfMeasure $baseUnitOfMeasure
 */
#[Fillable([
    'organization_id',
    'base_unit_of_measure_id',
    'name',
    'sku',
    'active',
])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    /**
     * Get the organization owning this item.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the authoritative stock base UOM for this item.
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
     * Get alternate item-specific UOM conversions.
     *
     * @return HasMany<InventoryItemUnit, $this>
     */
    public function unitConversions(): HasMany
    {
        return $this->hasMany(InventoryItemUnit::class);
    }

    /**
     * Cast persisted item state.
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
