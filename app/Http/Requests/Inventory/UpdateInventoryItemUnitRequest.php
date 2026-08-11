<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\InventoryItem;
use App\Models\InventoryItemUnit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateInventoryItemUnitRequest extends FormRequest
{
    /**
     * Restrict conversion updates to the active tenant and item.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && $this->inventoryItem() !== null
            && $this->inventoryItemUnit() !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            );
    }

    /**
     * Validate mutable conversion attributes.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
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
     * Resolve the parent item from the active organization.
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
     * Resolve the conversion only through the tenant-scoped parent item.
     */
    public function inventoryItemUnit(): ?InventoryItemUnit
    {
        $inventoryItem = $this->inventoryItem();
        $routeId = $this->route('inventoryItemUnit');

        if (
            $inventoryItem === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return $inventoryItem
            ->unitConversions()
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
