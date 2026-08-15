<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $from_location_id
 * @property int $from_storage_location_id
 * @property int $to_location_id
 * @property int $to_storage_location_id
 * @property string $number
 * @property StockTransferStatus $status
 * @property Carbon|null $requested_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $received_at
 * @property int|null $created_by
 * @property int|null $shipped_by
 * @property int|null $received_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'from_location_id',
    'from_storage_location_id',
    'to_location_id',
    'to_storage_location_id',
    'number',
    'status',
    'requested_at',
    'shipped_at',
    'received_at',
    'created_by',
    'shipped_by',
    'received_by',
    'notes',
])]
class StockTransfer extends Model
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
     * Get the source restaurant location.
     *
     * @return BelongsTo<Location, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(
            Location::class,
            'from_location_id',
        );
    }

    /**
     * Get the source storage location.
     *
     * @return BelongsTo<StorageLocation, $this>
     */
    public function fromStorageLocation(): BelongsTo
    {
        return $this->belongsTo(
            StorageLocation::class,
            'from_storage_location_id',
        );
    }

    /**
     * Get the destination restaurant location.
     *
     * @return BelongsTo<Location, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(
            Location::class,
            'to_location_id',
        );
    }

    /**
     * Get the destination storage location.
     *
     * @return BelongsTo<StorageLocation, $this>
     */
    public function toStorageLocation(): BelongsTo
    {
        return $this->belongsTo(
            StorageLocation::class,
            'to_storage_location_id',
        );
    }

    /**
     * Get immutable transfer line evidence.
     *
     * @return HasMany<StockTransferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    /**
     * Get the user who created the transfer.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who shipped the transfer.
     *
     * @return BelongsTo<User, $this>
     */
    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    /**
     * Get the user who received the transfer.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Cast lifecycle state and timestamps.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'requested_at' => 'datetime',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
