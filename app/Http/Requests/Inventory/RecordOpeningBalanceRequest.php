<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RecordOpeningBalanceRequest extends FormRequest
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
     * Validate one tenant-safe manually entered opening balance.
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
                'gt:0',
                'decimal:0,6',
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
            'base_unit_cost' => [
                'required',
                'numeric',
                'gte:0',
                'decimal:0,4',
                'max:99999999999.9999',
            ],
            'occurred_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
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
        $baseUnitCost = $this->input('base_unit_cost');
        $notes = $this->input('notes');

        $this->merge([
            'operation_id' => is_string($operationId)
                ? strtolower(trim($operationId))
                : $operationId,
            'quantity' => is_string($quantity)
                ? trim($quantity)
                : $quantity,
            'base_unit_cost' => is_string($baseUnitCost)
                ? trim($baseUnitCost)
                : $baseUnitCost,
            'notes' => is_string($notes)
                && trim($notes) !== ''
                    ? trim($notes)
                    : null,
        ]);
    }
}
