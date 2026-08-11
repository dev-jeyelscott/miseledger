<?php

namespace App\Http\Requests\Organizations;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    /**
     * Require an authenticated, verified user.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasVerifiedEmail();
    }

    /**
     * Validate the minimum Phase 0 organization input.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
        ];
    }

    /**
     * Normalize the organization name before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge([
                'name' => trim($name),
            ]);
        }
    }
}
