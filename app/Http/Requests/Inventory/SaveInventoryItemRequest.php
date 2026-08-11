<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveInventoryItemRequest extends FormRequest
{
    /**
     * Require inventory-adjust permission and a tenant-safe update target.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (
            ! $user instanceof User
            || $organization === null
            || ! Gate::forUser($user)->allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            )
        ) {
            return false;
        }

        return $this->route('inventoryItem') === null
            || $this->inventoryItem() !== null;
    }

    /**
     * Validate an organization-owned inventory item.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->organization()?->id ?? 0;
        $inventoryItem = $this->inventoryItem();

        return [
            'name' => [
                'required',
                'string',
                'max:160',
            ],
            'sku' => [
                'required',
                'string',
                'max:80',
                Rule::unique('inventory_items', 'sku')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($inventoryItem),
            ],
            'base_unit_of_measure_id' => [
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->where(
                                'organization_id',
                                $organizationId,
                            )
                            ->where('active', true),
                    ),
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Return the tenancy middleware's active organization.
     */
    public function organization(): ?Organization
    {
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    /**
     * Resolve an update item only inside the active organization.
     */
    public function inventoryItem(): ?InventoryItem
    {
        $organization = $this->organization();
        $routeId = $this->route('inventoryItem');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return InventoryItem::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }

    /**
     * Normalize display values and SKU identity.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $sku = $this->input('sku');

        $this->merge([
            'name' => is_string($name)
                ? Str::squish($name)
                : $name,
            'sku' => is_string($sku)
                ? Str::upper(trim($sku))
                : $sku,
            'active' => $this->boolean('active'),
        ]);
    }
}
