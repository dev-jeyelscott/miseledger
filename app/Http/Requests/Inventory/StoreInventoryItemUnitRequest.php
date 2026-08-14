<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventoryItemUnitRequest extends FormRequest
{
    /**
     * Restrict conversion creation to the active tenant.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && $this->inventoryItem() !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            );
    }

    /**
     * Validate an item-specific conversion with fixed precision.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) (
            $this->organization()?->getKey() ?? 0
        );

        $inventoryItem = $this->inventoryItem();

        $inventoryItemId = (int) (
            $inventoryItem?->getKey() ?? 0
        );

        return [
            'unit_of_measure_id' => [
                'required',
                'integer',
                Rule::notIn([
                    $inventoryItem?->base_unit_of_measure_id,
                ]),
                Rule::exists('units_of_measure', 'id')
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->where(
                                'organization_id',
                                $organizationId,
                            )
                            ->where('active', true),
                    ),
                Rule::unique(
                    'inventory_item_units',
                    'unit_of_measure_id',
                )->where(
                    fn (Builder $query): Builder => $query->where(
                        'inventory_item_id',
                        $inventoryItemId,
                    ),
                ),
            ],
            'quantity_in_base_unit' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Require alternate units to share the base unit's physical dimension.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('unit_of_measure_id')) {
                return;
            }

            $inventoryItem = $this->inventoryItem();
            $unitOfMeasureId = $this->integer('unit_of_measure_id');

            if ($inventoryItem === null || $unitOfMeasureId === 0) {
                return;
            }

            $baseDimension = UnitOfMeasure::query()
                ->where('organization_id', $inventoryItem->organization_id)
                ->whereKey($inventoryItem->base_unit_of_measure_id)
                ->value('dimension');

            $alternateDimension = UnitOfMeasure::query()
                ->where('organization_id', $inventoryItem->organization_id)
                ->whereKey($unitOfMeasureId)
                ->value('dimension');

            if (
                $baseDimension !== null
                && $alternateDimension !== null
                && $baseDimension !== $alternateDimension
            ) {
                $validator->errors()->add(
                    'unit_of_measure_id',
                    __('The alternate unit must have the same dimension as the base unit.'),
                );
            }
        }];
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
     * Resolve the item only from the active organization.
     */
    public function inventoryItem(): ?InventoryItem
    {
        $organization = $this->organization();
        $routeId = $this->route('inventoryItem');

        if (
            $organization === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }

    /**
     * Normalize conversion values before validation.
     */
    protected function prepareForValidation(): void
    {
        $quantity = $this->input('quantity_in_base_unit');

        $this->merge([
            'quantity_in_base_unit' => is_string($quantity)
                ? trim($quantity)
                : $quantity,
            'active' => $this->boolean('active'),
        ]);
    }
}
