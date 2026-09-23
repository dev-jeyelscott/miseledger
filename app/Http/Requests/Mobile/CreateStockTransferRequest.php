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

/**
 * Validate the source/destination selection made on the mobile transfer
 * picker (Spec 6 §"Behavior and Flow" step 1), mirroring the location and
 * storage-location rules of desktop's `App\Http\Requests\Inventory\SaveStockTransferRequest`.
 * The source location is never taken from client input: `from_location_id`
 * is always `mobileActiveLocation`, matching `RecordWasteRequest`'s pattern
 * of forcing tenant-scoping fields server-side.
 */
class CreateStockTransferRequest extends FormRequest
{
    /**
     * Require transfer creation permission and an active mobile location.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && $this->activeLocation() !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::TransfersCreate->value,
                $organization,
            );
    }

    /**
     * Validate the tenant-safe destination selection.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;
        $activeLocation = $this->activeLocation();
        $fromLocationId = $activeLocation !== null ? $activeLocation->id : 0;
        $toLocationId = (int) $this->input('to_location_id', 0);

        return [
            'from_storage_location_id' => [
                'required',
                'integer',
                Rule::exists('storage_locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('location_id', $fromLocationId)
                        ->where('active', true),
                ),
            ],
            'to_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'to_storage_location_id' => [
                'required',
                'integer',
                'different:from_storage_location_id',
                Rule::exists('storage_locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('location_id', $toLocationId)
                        ->where('active', true),
                ),
            ],
            'item_id' => [
                'nullable',
                'integer',
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
