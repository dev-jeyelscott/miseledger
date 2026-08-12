<?php

namespace App\Models;

use Database\Factories\SupplierItemPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_item_id
 * @property string $price
 * @property string $currency
 * @property Carbon $effective_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'organization_id',
    'supplier_item_id',
    'price',
    'currency',
    'effective_at',
])]
class SupplierItemPrice extends Model
{
    /** @use HasFactory<SupplierItemPriceFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the organization owning the price record.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the supplier item this historical price belongs to.
     *
     * @return BelongsTo<SupplierItem, $this>
     */
    public function supplierItem(): BelongsTo
    {
        return $this->belongsTo(SupplierItem::class);
    }

    /**
     * Preserve money precision and price timestamps.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'effective_at' => 'immutable_datetime',
        ];
    }
}
