<?php

namespace App\Http\Requests\Inventory;

use App\Enums\BarcodeSymbology;
use App\Enums\OrganizationPermission;
use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreBarcodeRequest extends FormRequest
{
    /**
     * Restrict barcode creation to the active tenant.
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
     * Validate a tenant-scoped barcode identity.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) (
            $this->organization()?->getKey() ?? 0
        );

        $inventoryItemId = (int) (
            $this->inventoryItem()?->getKey() ?? 0
        );

        return [
            'value' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('barcodes', 'value')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    ),
            ],
            'symbology' => [
                'required',
                Rule::enum(BarcodeSymbology::class),
            ],
            'inventory_item_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_item_units', 'id')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'inventory_item_id',
                            $inventoryItemId,
                        ),
                    ),
            ],
            'is_primary' => [
                'required',
                'boolean',
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
     * Resolve the parent item only from the active organization.
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
     * Normalize the barcode value and flags before validation.
     */
    protected function prepareForValidation(): void
    {
        $value = $this->input('value');
        $unitId = $this->input('inventory_item_unit_id');

        $this->merge([
            'value' => is_string($value)
                ? Str::upper(trim($value))
                : $value,
            'inventory_item_unit_id' => $unitId === '' ? null : $unitId,
            'is_primary' => $this->boolean('is_primary'),
            'active' => $this->boolean('active'),
        ]);
    }
}
