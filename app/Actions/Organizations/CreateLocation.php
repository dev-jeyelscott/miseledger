<?php

namespace App\Actions\Organizations;

use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Support\Billing\OrganizationUsageLimitEnforcer;
use App\Support\Billing\UsageLimitKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateLocation
{
    /**
     * Create a location and its default storage area atomically, within the
     * organization's plan location limit.
     *
     * @param  array{name: string, code: string}  $attributes
     */
    public function handle(
        Organization $organization,
        array $attributes,
    ): Location {
        return DB::transaction(function () use (
            $organization,
            $attributes,
        ): Location {
            $lockedOrganization = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            OrganizationUsageLimitEnforcer::assertCanAdd(
                lockedOrganization: $lockedOrganization,
                limitKey: UsageLimitKey::Locations,
                currentUsage: $lockedOrganization->locations()->count(),
                errorField: 'name',
                errorMessage: __('This organization has reached its location limit for the current plan.'),
            );

            $location = $lockedOrganization
                ->locations()
                ->create($attributes);

            $storageLocation = new StorageLocation([
                'name' => StorageLocation::DEFAULT_NAME,
                'code' => StorageLocation::DEFAULT_CODE,
                'active' => true,
            ]);

            $storageLocation
                ->organization()
                ->associate($lockedOrganization);

            $storageLocation
                ->location()
                ->associate($location);

            $storageLocation->save();

            return $location;
        });
    }

    /**
     * Derive an unused location code from a human-entered name. Call inside
     * the creating transaction's organization lock scope when uniqueness
     * matters; the database unique constraint remains the final guard.
     */
    public static function deriveCode(
        Organization $organization,
        string $name,
    ): string {
        $base = Str::upper(Str::slug($name));
        $base = Str::limit($base, 24, '');
        $base = rtrim($base, '-');

        if ($base === '') {
            $base = 'LOC';
        }

        $existingCodes = $organization->locations()
            ->where('code', 'like', $base.'%')
            ->pluck('code')
            ->all();

        $code = $base;
        $suffix = 2;

        while (in_array($code, $existingCodes, true)) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
