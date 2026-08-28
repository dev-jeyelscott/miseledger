<?php

namespace App\Models;

use Database\Factories\InventoryProductOptionValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $inventory_product_option_id
 * @property string $value
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read InventoryProductOption $inventoryProductOption
 */
#[Fillable(['organization_id', 'inventory_product_option_id', 'value', 'active'])]
class InventoryProductOptionValue extends Model
{
    /** @use HasFactory<InventoryProductOptionValueFactory> */
    use HasFactory;

    /**
     * Get the organization owning this option value.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the option dimension owning this value.
     *
     * @return BelongsTo<InventoryProductOption, $this>
     */
    public function inventoryProductOption(): BelongsTo
    {
        return $this->belongsTo(InventoryProductOption::class);
    }

    /**
     * Cast persisted option value state.
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
