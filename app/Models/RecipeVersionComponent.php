<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $recipe_version_id
 * @property int $inventory_item_id
 * @property string $quantity
 * @property int $unit_of_measure_id
 * @property string $base_quantity
 * @property string $yield_percentage
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'recipe_version_id',
    'inventory_item_id',
    'quantity',
    'unit_of_measure_id',
    'base_quantity',
    'yield_percentage',
    'notes',
])]
class RecipeVersionComponent extends Model
{
    /**
     * Get the recipe version this component belongs to.
     *
     * @return BelongsTo<RecipeVersion, $this>
     */
    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    /**
     * Get the inventory item consumed by this component.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the unit the component quantity is entered in.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * Preserve quantity and yield values as fixed-precision strings.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'base_quantity' => 'decimal:6',
            'yield_percentage' => 'decimal:2',
        ];
    }
}
