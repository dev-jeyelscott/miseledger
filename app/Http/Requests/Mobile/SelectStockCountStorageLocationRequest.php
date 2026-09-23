<?php

namespace App\Http\Requests\Mobile;

use App\Enums\OrganizationPermission;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SelectStockCountStorageLocationRequest extends FormRequest
{
    /**
     * Require physical-count creation permission and an active tenant.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::CountsCreate->value,
                $organization,
            );
    }

    /**
     * Validate the chosen storage location belongs to the active location.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;
        $location = $this->activeLocation();
        $locationId = $location !== null ? $location->id : 0;

        return [
            'storage_location_id' => [
                'required',
                'integer',
                Rule::exists('storage_locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('location_id', $locationId)
                        ->where('active', true),
                ),
            ],
        ];
    }

    /**
     * Return the active organization.
     */
    public function organization(): ?Organization
    {
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    /**
     * Return the active mobile location resolved by `ResolveMobileLocation`.
     */
    public function activeLocation(): ?Location
    {
        $location = $this->attributes->get('mobileActiveLocation');

        return $location instanceof Location ? $location : null;
    }
}
