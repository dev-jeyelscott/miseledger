<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $location_id
 * @property string $name
 * @property string $code
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'code', 'active'])]
class StorageLocation extends Model
{
    public const DEFAULT_NAME = 'Default Storage';

    public const DEFAULT_CODE = 'DEFAULT';

    /**
     * Get the organization owning this physical storage location.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the restaurant location containing this storage location.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Cast persisted storage-location state.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Reject an organization/location pairing that crosses tenant boundaries.
     */
    protected static function booted(): void
    {
        static::saving(static function (StorageLocation $storageLocation): void {
            if (! $storageLocation->isDirty([
                'organization_id',
                'location_id',
            ])) {
                return;
            }

            $locationBelongsToOrganization = Location::query()
                ->whereKey($storageLocation->location_id)
                ->where(
                    'organization_id',
                    $storageLocation->organization_id,
                )
                ->exists();

            if (! $locationBelongsToOrganization) {
                throw ValidationException::withMessages([
                    'location_id' => __(
                        'The selected location does not belong to the organization.',
                    ),
                ]);
            }
        });
    }
}
