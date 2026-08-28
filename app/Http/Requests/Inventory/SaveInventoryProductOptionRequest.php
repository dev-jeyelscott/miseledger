<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveInventoryProductOptionRequest extends FormRequest
{
    /**
     * Authorize option mutations within the active organization and product family.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && Gate::forUser($user)->allows(OrganizationPermission::InventoryAdjust->value, $organization)
            && $this->inventoryProduct() !== null
            && ($this->route('inventoryProductOption') === null || $this->inventoryProductOption() !== null);
    }

    /**
     * Get validation rules for a controlled product option.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_product_options', 'name')
                    ->where(fn (Builder $query): Builder => $query->where(
                        'inventory_product_id',
                        $this->inventoryProduct()->id ?? 0,
                    ))
                    ->ignore($this->inventoryProductOption()),
            ],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * Return the active organization resolved by tenancy middleware.
     */
    public function organization(): ?Organization
    {
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization ? $organization : null;
    }

    /**
     * Resolve the routed product family inside the active organization.
     */
    public function inventoryProduct(): ?InventoryProduct
    {
        $organization = $this->organization();
        $routeId = $this->route('inventoryProduct');

        if ($organization === null || ! is_numeric($routeId)) {
            return null;
        }

        return $organization->inventoryProducts()->find((int) $routeId);
    }

    /**
     * Resolve the routed option inside the active product family.
     */
    public function inventoryProductOption(): ?InventoryProductOption
    {
        $product = $this->inventoryProduct();
        $routeId = $this->route('inventoryProductOption');

        if ($product === null || ! is_numeric($routeId)) {
            return null;
        }

        return $product->options()->find((int) $routeId);
    }

    /**
     * Normalize option input before authorization and validation complete.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        $this->merge([
            'name' => is_string($name) ? Str::squish($name) : $name,
            'active' => $this->boolean('active'),
        ]);
    }
}
