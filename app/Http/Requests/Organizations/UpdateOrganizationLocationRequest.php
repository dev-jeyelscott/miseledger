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

class UpdateOrganizationLocationRequest extends FormRequest
{
    /**
     * Restrict location updates to the owning organization and locations.manage.
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
     * Validate an organization location update.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $location = $this->route('location');

        $organizationId = $organization instanceof Organization
            ? $organization->getKey()
            : 0;

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
                'max:32',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('locations', 'code')
                    ->where(
                        static fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($locationId),
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Normalize human-entered location values before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $code = $this->input('code');

        $normalized = [];

        if (is_string($name)) {
            $normalized['name'] = trim($name);
        }

        if (is_string($code)) {
            $normalized['code'] = Str::upper(trim($code));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
