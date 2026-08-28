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

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'value' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_product_option_values', 'value')
                    ->where(fn (Builder $query): Builder => $query->where('inventory_product_option_id', $this->inventoryProductOption()?->id ?? 0))
                    ->ignore($this->inventoryProductOptionValue()),
            ],
            'active' => ['required', 'boolean'],
        ];
    }

    public function organization(): ?Organization
    {
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization ? $organization : null;
    }

    public function inventoryProduct(): ?InventoryProduct
    {
        $organization = $this->organization();
        $routeId = $this->route('inventoryProduct');

        if ($organization === null || ! is_numeric($routeId)) {
            return null;
        }

        return $organization->inventoryProducts()->find((int) $routeId);
    }

    public function inventoryProductOption(): ?InventoryProductOption
    {
        $product = $this->inventoryProduct();
        $routeId = $this->route('inventoryProductOption');

        if ($product === null || ! is_numeric($routeId)) {
            return null;
        }

        return $product->options()->find((int) $routeId);
    }

    public function inventoryProductOptionValue(): ?InventoryProductOptionValue
    {
        $option = $this->inventoryProductOption();
        $routeId = $this->route('inventoryProductOptionValue');

        if ($option === null || ! is_numeric($routeId)) {
            return null;
        }

        return $option->values()->find((int) $routeId);
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('value');

        $this->merge([
            'value' => is_string($value) ? Str::squish($value) : $value,
            'active' => $this->boolean('active'),
        ]);
    }
}
