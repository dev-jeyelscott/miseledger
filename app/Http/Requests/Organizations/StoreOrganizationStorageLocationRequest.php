<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationPermission;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreOrganizationStorageLocationRequest extends FormRequest
{
    /**
     * Reuse location-management authorization and require parent ownership.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->route('organization');
        $location = $this->route('location');

        return $user instanceof User
            && $organization instanceof Organization
            && $location instanceof Location
            && $location->organization_id === $organization->getKey()
            && Gate::forUser($user)->allows(
                OrganizationPermission::LocationsManage->value,
                $organization,
            );
    }

    /**
     * Validate a storage location without accepting tenant IDs from input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $location = $this->route('location');

        $locationId = $location instanceof Location
            ? $location->getKey()
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
                    ),
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
