<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\OrganizationPermission;
use App\Support\Inventory\StandardUnits;
use Illuminate\Validation\Rule;

class StoreOnboardingUnitsRequest extends OnboardingRequest
{
    protected function permission(): OrganizationPermission
    {
        return OrganizationPermission::InventoryAdjust;
    }

    /**
     * Only symbols from the predefined standard catalog may be selected.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'symbols' => ['required', 'array', 'min:1'],
            'symbols.*' => [
                'required',
                'string',
                Rule::in(array_column(StandardUnits::definitions(), 'symbol')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'symbols.required' => __('Select at least one unit.'),
            'symbols.min' => __('Select at least one unit.'),
        ];
    }
}
