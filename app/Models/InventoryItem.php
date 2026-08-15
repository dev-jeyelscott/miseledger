<?php

namespace App\Models;

use App\Enums\InventoryItemType;
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
 * @property int|null $inventory_category_id
 * @property string $name
 * @property string $sku
 * @property InventoryItemType $type
 * @property string $yield_percentage
 * @property bool $active
 * @property int|null $unit_conversions_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UnitOfMeasure $baseUnitOfMeasure
 */
#[Fillable([
    'organization_id',
    'base_unit_of_measure_id',
    'inventory_category_id',
    'name',
    'sku',
    'type',
    'yield_percentage',
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
     * Get the optional category assigned to this item.
     *
     * @return BelongsTo<InventoryCategory, $this>
     */
    public function inventoryCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
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
     * Get the committed ledger movements recorded against this item.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
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
            'type' => InventoryItemType::class,
            'yield_percentage' => 'decimal:2',
        ];
    }
}
