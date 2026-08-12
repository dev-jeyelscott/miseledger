<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
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
 * @property string $code
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $payment_terms
 * @property int|null $lead_time_days
 * @property bool $active
 * @property int|null $item_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'name',
    'code',
    'contact_name',
    'email',
    'phone',
    'payment_terms',
    'lead_time_days',
    'active',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /**
     * Get the organization owning the supplier.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get purchase-pack mappings provided by this supplier.
     *
     * @return HasMany<SupplierItem, $this>
     */
    public function supplierItems(): HasMany
    {
        return $this->hasMany(SupplierItem::class);
    }

    /**
     * Cast supplier state to stable application types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_time_days' => 'integer',
            'active' => 'boolean',
        ];
    }
}
