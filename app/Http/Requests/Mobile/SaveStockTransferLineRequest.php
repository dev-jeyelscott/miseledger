<?php

namespace App\Http\Requests\Mobile;

use App\Enums\OrganizationPermission;
use App\Enums\StockTransferStatus;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validate one scanned transfer line (Spec 6 §"Behavior and Flow" step 2),
 * mirroring `App\Http\Requests\Mobile\SaveStockCountLineRequest`'s
 * accumulating-draft shape: either `stock_transfer_id` resumes an existing
 * draft, or the destination fields from `CreateStockTransferRequest` start
 * one lazily on the first accepted line.
 */
class SaveStockTransferLineRequest extends FormRequest
{
    /**
     * Server-authoritative bound mirrored client-side for instant feedback,
     * matching `SaveStockTransfer::MAX_QUANTITY`.
     */
    private const MAX_QUANTITY = '999999999.999999';

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
     * Validate one scanned transfer line against an accumulating draft.
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
            'stock_transfer_id' => [
                'nullable',
                'integer',
                Rule::exists('stock_transfers', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('from_location_id', $fromLocationId)
                        ->where('status', StockTransferStatus::Draft->value),
                ),
            ],
            'from_storage_location_id' => [
                'required_without:stock_transfer_id',
                'nullable',
                'integer',
                Rule::exists('storage_locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('location_id', $fromLocationId)
                        ->where('active', true),
                ),
            ],
            'to_location_id' => [
                'required_without:stock_transfer_id',
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'to_storage_location_id' => [
                'required_without:stock_transfer_id',
                'nullable',
                'integer',
                Rule::exists('storage_locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('location_id', $toLocationId)
                        ->where('active', true),
                ),
            ],
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'quantity' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,6',
                'max:'.self::MAX_QUANTITY,
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
