<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RecordInventoryAdjustmentRequest extends FormRequest
{
    /**
     * Require privileged inventory-adjust permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            );
    }

    /**
     * Validate one tenant-safe manual stock correction.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;
        $locationId = (int) $this->input(
            'location_id',
            0,
        );

        return [
            'operation_id' => [
                'required',
                'uuid',
            ],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where(
                            'organization_id',
                            $organizationId,
                        )
                        ->where('active', true),
                ),
            ],
            'storage_location_id' => [
                'required',
                'integer',
                Rule::exists(
                    'storage_locations',
                    'id',
                )->where(
                    fn (Builder $query): Builder => $query
                        ->where(
                            'organization_id',
                            $organizationId,
                        )
                        ->where(
                            'location_id',
                            $locationId,
                        )
                        ->where('active', true),
                ),
            ],
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists(
                    'inventory_items',
                    'id',
                )->where(
                    fn (Builder $query): Builder => $query
                        ->where(
                            'organization_id',
                            $organizationId,
                        )
                        ->where('active', true),
                ),
            ],
            'quantity' => [
                'required',
                'numeric',
                'not_in:0',
                'decimal:0,6',
                'min:-999999999.999999',
                'max:999999999.999999',
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists(
                    'units_of_measure',
                    'id',
                )->where(
                    fn (Builder $query): Builder => $query
                        ->where(
                            'organization_id',
                            $organizationId,
                        )
                        ->where('active', true),
                ),
            ],
            'reason' => [
                'required',
                'string',
                'max:2000',
            ],
            'occurred_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
            ],
        ];
    }

    /**
     * Return the active organization middleware context.
     */
    public function organization(): ?Organization
    {
        $organization = $this->attributes->get(
            'activeOrganization',
        );

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    /**
     * Normalize immutable operation input.
     */
    protected function prepareForValidation(): void
    {
        $operationId = $this->input('operation_id');
        $quantity = $this->input('quantity');
        $reason = $this->input('reason');

        $this->merge([
            'operation_id' => is_string($operationId)
                ? strtolower(trim($operationId))
                : $operationId,
            'quantity' => is_string($quantity)
                ? trim($quantity)
                : $quantity,
            'reason' => is_string($reason)
                ? trim($reason)
                : $reason,
        ]);
    }
}
