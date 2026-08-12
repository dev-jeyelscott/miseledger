<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationPermission;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateOrganizationStorageLocationRequest extends FormRequest
{
    /**
     * Require ownership across organization, location, and storage records.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->route('organization');
        $location = $this->route('location');
        $storageLocation = $this->route('storageLocation');

        return $user instanceof User
            && $organization instanceof Organization
            && $location instanceof Location
            && $storageLocation instanceof StorageLocation
            && $location->organization_id === $organization->getKey()
            && $storageLocation->organization_id
                === $organization->getKey()
            && $storageLocation->location_id === $location->getKey()
            && Gate::forUser($user)->allows(
                OrganizationPermission::LocationsManage->value,
                $organization,
            );
    }

    /**
     * Validate mutable storage master data.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $location = $this->route('location');
        $storageLocation = $this->route('storageLocation');

        $locationId = $location instanceof Location
            ? $location->getKey()
            : 0;

        $storageLocationId = $storageLocation instanceof StorageLocation
            ? $storageLocation->getKey()
            : 0;

        return [
            'name' => [
                'required',
                'string',
                'max:160',
            ],
            'code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('storage_locations', 'code')
                    ->where(
                        static fn (Builder $query): Builder => $query->where(
                            'location_id',
                            $locationId,
                        ),
                    )
                    ->ignore($storageLocationId),
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Normalize storage-location master data before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $code = $this->input('code');

        $this->merge([
            'name' => is_string($name)
                ? Str::squish($name)
                : $name,
            'code' => is_string($code)
                ? Str::upper(trim($code))
                : $code,
            'active' => $this->boolean('active'),
        ]);
    }
}
