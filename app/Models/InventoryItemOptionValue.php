<?php

namespace App\Models;

use Database\Factories\InventoryItemOptionValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $inventory_item_id
 * @property int $inventory_product_option_value_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read InventoryItem $inventoryItem
 * @property-read InventoryProductOptionValue $inventoryProductOptionValue
 */
#[Fillable([
    'organization_id',
    'inventory_item_id',
    'inventory_product_option_value_id',
])]
class InventoryItemOptionValue extends Model
{
    /** @use HasFactory<InventoryItemOptionValueFactory> */
    use HasFactory;

    /**
     * Get the organization owning this association.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the variant item carrying this association.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the controlled option value assigned to the item.
     *
     * @return BelongsTo<InventoryProductOptionValue, $this>
     */
    public function inventoryProductOptionValue(): BelongsTo
    {
        return $this->belongsTo(InventoryProductOptionValue::class);
    }
}
