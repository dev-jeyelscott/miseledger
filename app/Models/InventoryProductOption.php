<?php

namespace App\Models;

use Database\Factories\InventoryProductOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $inventory_product_id
 * @property string $name
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read InventoryProduct $inventoryProduct
 */
#[Fillable(['organization_id', 'inventory_product_id', 'name', 'active'])]
class InventoryProductOption extends Model
{
    /** @use HasFactory<InventoryProductOptionFactory> */
    use HasFactory;

    /**
     * Get the organization owning this option dimension.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the product family owning this option dimension.
     *
     * @return BelongsTo<InventoryProduct, $this>
     */
    public function inventoryProduct(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class);
    }

    /**
     * Get controlled values scoped to this option dimension.
     *
     * @return HasMany<InventoryProductOptionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(InventoryProductOptionValue::class);
    }

    /**
     * Cast persisted option state.
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
