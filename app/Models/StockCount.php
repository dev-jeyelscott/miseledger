<?php

namespace App\Models;

use App\Enums\StockCountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $location_id
 * @property int $storage_location_id
 * @property string $number
 * @property StockCountStatus $status
 * @property Carbon|null $counted_at
 * @property int|null $created_by
 * @property int|null $submitted_by
 * @property int|null $finalized_by
 * @property Carbon|null $finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'location_id',
    'storage_location_id',
    'number',
    'status',
    'counted_at',
    'created_by',
    'submitted_by',
    'finalized_by',
    'finalized_at',
])]
class StockCount extends Model
{
    /**
     * Get the organization owning this count.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the restaurant location containing the counted storage.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the physical storage location being counted.
     *
     * @return BelongsTo<StorageLocation, $this>
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Get physical-count evidence lines.
     *
     * @return HasMany<StockCountLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    /**
     * Get the user who created the draft.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who submitted the physical count.
     *
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who finalized inventory reconciliation.
     *
     * @return BelongsTo<User, $this>
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * Cast persisted count lifecycle fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockCountStatus::class,
            'counted_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }
}
