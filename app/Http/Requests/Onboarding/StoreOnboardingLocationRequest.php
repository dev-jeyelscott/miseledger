<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\OrganizationPermission;

class StoreOnboardingLocationRequest extends OnboardingRequest
{
    protected function permission(): OrganizationPermission
    {
        return OrganizationPermission::LocationsManage;
    }

    /**
     * A setup location needs only a name; its code is derived.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }
}
