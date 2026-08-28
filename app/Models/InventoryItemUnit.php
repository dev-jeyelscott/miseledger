<?php

namespace App\Models;

use Database\Factories\InventoryItemUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inventory_item_id
 * @property int $unit_of_measure_id
 * @property string $quantity_in_base_unit
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryItem $inventoryItem
 * @property-read UnitOfMeasure $unitOfMeasure
 */
#[Fillable([
    'inventory_item_id',
    'unit_of_measure_id',
    'quantity_in_base_unit',
    'active',
])]
class InventoryItemUnit extends Model
{
    /** @use HasFactory<InventoryItemUnitFactory> */
    use HasFactory;

    /**
     * Get the inventory item owning this conversion.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the alternate UOM represented by this conversion.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * Get barcodes specifically identifying this alternate item unit.
     *
     * @return HasMany<InventoryItemBarcode, $this>
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(InventoryItemBarcode::class);
    }

    /**
     * Preserve conversion precision as decimal strings.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_in_base_unit' => 'decimal:6',
            'active' => 'boolean',
        ];
    }
}
