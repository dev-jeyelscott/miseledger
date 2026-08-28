<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\InventoryBrand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveInventoryBrandRequest extends FormRequest
{
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

        return $this->route('inventoryBrand') === null
            || $this->inventoryBrand() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) (
            $this->organization()?->getKey() ?? 0
        );

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_brands', 'name')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($this->inventoryBrand()),
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function organization(): ?Organization
    {
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    public function inventoryBrand(): ?InventoryBrand
    {
        $organization = $this->organization();
        $routeId = $this->route('inventoryBrand');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return InventoryBrand::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        $this->merge([
            'name' => is_string($name)
                ? Str::squish($name)
                : $name,
            'active' => $this->boolean('active'),
        ]);
    }
}
