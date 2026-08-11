<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreOrganizationLocationRequest extends FormRequest
{
    /**
     * Restrict location creation to locations.manage.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->route('organization');

        return $user instanceof User
            && $organization instanceof Organization
            && Gate::forUser($user)->allows(
                OrganizationPermission::LocationsManage->value,
                $organization,
            );
    }

    /**
     * Validate a new organization-scoped location.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');

        $organizationId = $organization instanceof Organization
            ? $organization->getKey()
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
                Rule::unique('locations', 'code')->where(
                    static fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organizationId,
                    ),
                ),
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
