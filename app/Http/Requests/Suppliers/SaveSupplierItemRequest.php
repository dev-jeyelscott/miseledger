<?php

namespace App\Http\Requests\Suppliers;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveSupplierItemRequest extends FormRequest
{
    /**
     * Restrict supplier-item writes to purchasing managers in one tenant.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (
            ! $user instanceof User
            || $organization === null
            || $this->supplier() === null
            || ! Gate::forUser($user)->allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            )
        ) {
            return false;
        }

        return $this->route('supplierItem') === null
            || $this->supplierItem() !== null;
    }

    /**
     * Validate supplier SKU, internal item, unit, and exact base quantity.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) (
            $this->organization()?->getKey() ?? 0
        );

        $supplierId = (int) (
            $this->supplier()?->getKey() ?? 0
        );

        return [
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organizationId,
                    ),
                ),
            ],

            'supplier_sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('supplier_items', 'supplier_sku')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'supplier_id',
                            $supplierId,
                        ),
                    )
                    ->ignore($this->supplierItem()),
            ],

            'description' => [
                'nullable',
                'string',
                'max:255',
            ],

            'purchase_unit_of_measure_id' => [
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organizationId,
                    ),
                ),
            ],

            'base_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999.999999',
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
     * Resolve the parent supplier inside the active organization.
     */
    public function supplier(): ?Supplier
    {
        $organization = $this->organization();
        $routeId = $this->route('supplier');

        if (
            $organization === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return Supplier::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }

    /**
     * Resolve the supplier item through both tenant and supplier scopes.
     */
    public function supplierItem(): ?SupplierItem
    {
        $organization = $this->organization();
        $supplier = $this->supplier();
        $routeId = $this->route('supplierItem');

        if (
            $organization === null
            || $supplier === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return SupplierItem::query()
            ->where('organization_id', $organization->id)
            ->where('supplier_id', $supplier->id)
            ->find((int) $routeId);
    }

    /**
     * Normalize supplier-pack identity and fixed precision input.
     */
    protected function prepareForValidation(): void
    {
        $supplierSku = $this->input('supplier_sku');
        $description = $this->input('description');
        $baseQuantity = $this->input('base_quantity');

        $this->merge([
            'supplier_sku' => is_string($supplierSku)
                ? Str::upper(trim($supplierSku))
                : $supplierSku,

            'description' => is_string($description)
                && trim($description) !== ''
                    ? Str::squish($description)
                    : null,

            'base_quantity' => is_string($baseQuantity)
                ? trim($baseQuantity)
                : $baseQuantity,

            'active' => $this->boolean('active'),
        ]);
    }
}
