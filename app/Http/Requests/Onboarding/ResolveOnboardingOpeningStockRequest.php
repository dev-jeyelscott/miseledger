<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\OrganizationPermission;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class ResolveOnboardingOpeningStockRequest extends OnboardingRequest
{
    public const RESOLUTION_QUANTITY = 'quantity';

    public const RESOLUTION_NONE = 'none';

    protected function permission(): OrganizationPermission
    {
        return OrganizationPermission::InventoryAdjust;
    }

    /**
     * An explicit resolution is required; "none" is never inferred from a
     * blank or zero quantity.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'resolution' => [
                'required',
                Rule::in([self::RESOLUTION_QUANTITY, self::RESOLUTION_NONE]),
            ],
            'location_id' => [
                'exclude_unless:resolution,'.self::RESOLUTION_QUANTITY,
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', (int) $this->organization()?->getKey())
                        ->where('active', true),
                ),
            ],
            'quantity' => [
                'exclude_unless:resolution,'.self::RESOLUTION_QUANTITY,
                'required',
                'numeric',
                'gt:0',
                'decimal:0,6',
                'max:999999999.999999',
            ],
            'base_unit_cost' => [
                'exclude_unless:resolution,'.self::RESOLUTION_QUANTITY,
                'required',
                'numeric',
                'gte:0',
                'decimal:0,4',
                'max:99999999999.9999',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.gt' => __('Opening quantity must be greater than zero. Choose "No opening stock" if this item starts empty.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['quantity', 'base_unit_cost'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
