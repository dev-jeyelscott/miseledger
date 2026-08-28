<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\InventoryProduct;
use App\Models\InventoryProductOption;
use App\Models\InventoryProductOptionValue;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveInventoryProductOptionValueRequest extends FormRequest
{
    /**
     * Authorize option-value mutations within the active organization and option.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && Gate::forUser($user)->allows(OrganizationPermission::InventoryAdjust->value, $organization)
            && $this->inventoryProduct() !== null
            && $this->inventoryProductOption() !== null
            && ($this->route('inventoryProductOptionValue') === null || $this->inventoryProductOptionValue() !== null);
    }

    /**
     * Get validation rules for a controlled product option value.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'value' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_product_option_values', 'value')
                    ->where(fn (Builder $query): Builder => $query->where(
                        'inventory_product_option_id',
                        $this->inventoryProductOption()->id ?? 0,
                    ))
                    ->ignore($this->inventoryProductOptionValue()),
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
     * Resolve the routed option value inside the active option.
     */
    public function inventoryProductOptionValue(): ?InventoryProductOptionValue
    {
        $option = $this->inventoryProductOption();
        $routeId = $this->route('inventoryProductOptionValue');

        if ($option === null || ! is_numeric($routeId)) {
            return null;
        }

        return $option->values()->find((int) $routeId);
    }

    /**
     * Normalize option-value input before authorization and validation complete.
     */
    protected function prepareForValidation(): void
    {
        $value = $this->input('value');

        $this->merge([
            'value' => is_string($value) ? Str::squish($value) : $value,
            'active' => $this->boolean('active'),
        ]);
    }
}
