<?php

namespace App\Models;

use Database\Factories\SupplierItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_id
 * @property int $inventory_item_id
 * @property string $supplier_sku
 * @property string|null $description
 * @property int $purchase_unit_of_measure_id
 * @property string $base_quantity
 * @property string|null $current_price
 * @property string $currency
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read InventoryItem $inventoryItem
 * @property-read UnitOfMeasure $purchaseUnitOfMeasure
 */
#[Fillable([
    'organization_id',
    'supplier_id',
    'inventory_item_id',
    'supplier_sku',
    'description',
    'purchase_unit_of_measure_id',
    'base_quantity',
    'current_price',
    'currency',
    'active',
])]
class SupplierItem extends Model
{
    /** @use HasFactory<SupplierItemFactory> */
    use HasFactory;

    /**
     * Get the organization owning this supplier item.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the supplier providing this item.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the internal inventory item represented by this mapping.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the supplier purchase unit for this mapping.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function purchaseUnitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'purchase_unit_of_measure_id',
        );
    }

    /**
     * Get immutable historical prices for this supplier item.
     *
     * @return HasMany<SupplierItemPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(SupplierItemPrice::class);
    }

    /**
     * Resolve the current price directly from history, independent of the
     * cached current_price column, breaking ties deterministically.
     */
    public function currentPriceRecord(): ?SupplierItemPrice
    {
        return $this->prices()
            ->mostRecentFirst()
            ->first();
    }

    /**
     * Preserve authoritative quantities and prices as decimal strings.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_quantity' => 'decimal:6',
            'current_price' => 'decimal:4',
            'active' => 'boolean',
        ];
    }
}
