<?php

namespace App\Actions\Organizations;

use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveStorageLocation
{
    /**
     * Create or update storage configuration while preserving the
     * organization → location → storage ownership invariant.
     *
     * @param  array{name: string, code: string, active: bool}  $attributes
     */
    public function handle(
        Organization $organization,
        Location $location,
        array $attributes,
        ?StorageLocation $storageLocation = null,
    ): StorageLocation {
        return DB::transaction(function () use (
            $organization,
            $location,
            $attributes,
            $storageLocation,
        ): StorageLocation {
            $lockedLocation = Location::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($location->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedLocation === null) {
                throw ValidationException::withMessages([
                    'location' => __(
                        'The selected location does not belong to the organization.',
                    ),
                ]);
            }

            if ($storageLocation === null) {
                $storage = new StorageLocation($attributes);

                $storage
                    ->organization()
                    ->associate($organization);

                $storage
                    ->location()
                    ->associate($lockedLocation);

                $storage->save();

                return $storage;
            }

            $lockedStorage = StorageLocation::query()
                ->where('organization_id', $organization->getKey())
                ->where('location_id', $lockedLocation->getKey())
                ->whereKey($storageLocation->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedStorage === null) {
                throw ValidationException::withMessages([
                    'storage_location' => __(
                        'The storage location does not belong to the selected location.',
                    ),
                ]);
            }

            $lockedStorage->update($attributes);

            return $lockedStorage;
        });
    }
}
