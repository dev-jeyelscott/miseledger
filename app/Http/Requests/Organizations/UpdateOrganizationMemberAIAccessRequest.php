<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationPermission;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateOrganizationMemberAIAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->route('organization');

        if (! $user instanceof User || ! $organization instanceof Organization) {
            return false;
        }

        $isOwner = $user->organizationMemberships()
            ->whereBelongsTo($organization)
            ->where('role', OrganizationRole::Owner->value)
            ->exists();

        return $isOwner && Gate::forUser($user)->allows(
            OrganizationPermission::UsersManage->value,
            $organization,
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['ai_enabled' => ['required', 'boolean']];
    }
}
