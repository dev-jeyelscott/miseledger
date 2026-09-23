<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\OrganizationPermission;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class OnboardingInventoryImportRequest extends OnboardingRequest
{
    protected function permission(): OrganizationPermission
    {
        return OrganizationPermission::InventoryAdjust;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', (int) $this->organization()?->getKey())
                        ->where('active', true),
                ),
            ],
        ];
    }
}
