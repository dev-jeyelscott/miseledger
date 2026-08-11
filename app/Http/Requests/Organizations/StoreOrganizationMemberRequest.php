<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreOrganizationMemberRequest extends FormRequest
{
    /**
     * Restrict membership management to users with users.manage.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->route('organization');

        return $user instanceof User
            && $organization instanceof Organization
            && Gate::forUser($user)->allows(
                OrganizationPermission::UsersManage->value,
                $organization,
            );
    }

    /**
     * Validate the target registered user and assigned fixed role.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                'exists:users,email',
            ],
            'role' => [
                'required',
                Rule::enum(OrganizationRole::class),
            ],
        ];
    }

    /**
     * Normalize the email before resolving the registered user.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge([
                'email' => Str::lower(trim($email)),
            ]);
        }
    }
}
