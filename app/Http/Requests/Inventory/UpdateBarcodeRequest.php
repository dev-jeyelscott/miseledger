<?php

namespace App\Http\Requests\Inventory;

use App\Enums\BarcodeSymbology;
use App\Enums\OrganizationPermission;
use App\Models\InventoryItem;
use App\Models\InventoryItemBarcode;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBarcodeRequest extends FormRequest
{
    /**
     * Restrict barcode updates to the active tenant and item.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && $this->inventoryItem() !== null
            && $this->barcode() !== null
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
                Rule::unique('inventory_item_barcodes', 'barcode')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($this->barcode()),
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
     * Apply symbology-specific structural validation after base rules pass.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateBarcodeStructure($validator);
            },
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
     * Resolve the barcode only through the tenant-scoped parent item.
     */
    public function barcode(): ?InventoryItemBarcode
    {
        $inventoryItem = $this->inventoryItem();
        $routeId = $this->route('barcode');

        if (
            $inventoryItem === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return $inventoryItem
            ->barcodes()
            ->find((int) $routeId);
    }

    /**
     * Validate only safe structure rules for known barcode symbologies.
     */
    private function validateBarcodeStructure(Validator $validator): void
    {
        if (
            $validator->errors()->has('value')
            || $validator->errors()->has('symbology')
        ) {
            return;
        }

        $value = $this->input('value');
        $symbology = BarcodeSymbology::tryFrom(
            (string) $this->input('symbology'),
        );

        if (! is_string($value) || $symbology === null) {
            return;
        }

        $message = match ($symbology) {
            BarcodeSymbology::Ean13 => preg_match(
                '~\A[0-9]{13}\z~',
                $value,
            ) === 1
                ? null
                : 'EAN-13 barcodes must contain exactly 13 digits.',
            BarcodeSymbology::Ean8 => preg_match(
                '~\A[0-9]{8}\z~',
                $value,
            ) === 1
                ? null
                : 'EAN-8 barcodes must contain exactly 8 digits.',
            BarcodeSymbology::UpcA => preg_match(
                '~\A[0-9]{12}\z~',
                $value,
            ) === 1
                ? null
                : 'UPC-A barcodes must contain exactly 12 digits.',
            BarcodeSymbology::UpcE => preg_match(
                '~\A[0-9]{8}\z~',
                $value,
            ) === 1
                ? null
                : 'UPC-E barcodes must contain exactly 8 digits.',
            BarcodeSymbology::Code39 => preg_match(
                '~\A[0-9A-Z .$/+%-]+\z~',
                $value,
            ) === 1
                ? null
                : 'Code 39 barcodes may only contain A-Z, 0-9, spaces, and . - $ / + %.',
            BarcodeSymbology::Code128,
            BarcodeSymbology::Other => null,
        };

        if ($message !== null) {
            $validator->errors()->add('value', $message);
        }
    }

    /**
     * Trim accidental outer whitespace and normalize serialized form values.
     */
    protected function prepareForValidation(): void
    {
        $value = $this->input('value');
        $unitId = $this->input('inventory_item_unit_id');

        $this->merge([
            'value' => is_string($value) ? trim($value) : $value,
            'inventory_item_unit_id' => $unitId === '' ? null : $unitId,
            'is_primary' => $this->boolean('is_primary'),
            'active' => $this->boolean('active'),
        ]);
    }
}
