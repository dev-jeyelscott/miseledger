<?php

namespace App\Models;

use App\Enums\BarcodeSymbology;
use Database\Factories\InventoryItemBarcodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $inventory_item_id
 * @property int|null $inventory_item_unit_id
 * @property string $barcode
 * @property BarcodeSymbology $symbology
 * @property bool $primary
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read InventoryItem $inventoryItem
 * @property-read InventoryItemUnit|null $inventoryItemUnit
 */
#[Fillable([
    'organization_id',
    'inventory_item_id',
    'inventory_item_unit_id',
    'barcode',
    'symbology',
    'primary',
    'active',
])]
class InventoryItemBarcode extends Model
{
    /** @use HasFactory<InventoryItemBarcodeFactory> */
    use HasFactory;

    /**
     * Get the organization owning this barcode.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the inventory item identified by this barcode.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the optional alternate item unit identified by this barcode.
     *
     * @return BelongsTo<InventoryItemUnit, $this>
     */
    public function inventoryItemUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryItemUnit::class);
    }

    /**
     * Cast persisted barcode state to stable application types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'symbology' => BarcodeSymbology::class,
            'primary' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
