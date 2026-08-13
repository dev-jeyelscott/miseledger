<?php

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $location_id
 * @property int $purchase_order_id
 * @property int $supplier_id
 * @property string $number
 * @property GoodsReceiptStatus $status
 * @property Carbon|null $received_at
 * @property string|null $supplier_reference
 * @property int|null $received_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'location_id',
    'purchase_order_id',
    'supplier_id',
    'number',
    'status',
    'received_at',
    'supplier_reference',
    'received_by',
    'notes',
])]
class GoodsReceipt extends Model
{
    /**
     * Get the owning organization.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the receiving location.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the originating purchase order.
     *
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the supplier from the purchase order.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get receipt lines.
     *
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    /**
     * Get the user who finalized receipt.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Cast receipt lifecycle state.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'received_at' => 'datetime',
        ];
    }
}
