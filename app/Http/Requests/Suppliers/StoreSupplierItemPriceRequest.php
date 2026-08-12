<?php

namespace App\Http\Requests\Suppliers;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSupplierItemPriceRequest extends FormRequest
{
    /**
     * Restrict price changes to purchasing managers in the active tenant.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && $this->supplier() !== null
            && $this->supplierItem() !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            );
    }

    /**
     * Validate fixed-precision supplier pricing.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'price' => [
                'required',
                'numeric',
                'gte:0',
                'max:99999999999.9999',
                'decimal:0,4',
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
     * Resolve the supplier item through tenant and supplier scopes.
     */
    public function supplierItem(): ?SupplierItem
    {
        $organization = $this->organization();
        $supplier = $this->supplier();
        $routeId = $this->route('supplierItem');

        if (
            $organization === null
            || $supplier === null
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
     * Normalize the decimal input before validation.
     */
    protected function prepareForValidation(): void
    {
        $price = $this->input('price');

        $this->merge([
            'price' => is_string($price)
                ? trim($price)
                : $price,
        ]);
    }
}
