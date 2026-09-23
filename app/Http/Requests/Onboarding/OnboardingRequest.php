<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Shared active-organization and permission boundary for setup mutations.
 * The organization always comes from trusted server context, never input.
 */
abstract class OnboardingRequest extends FormRequest
{
    /**
     * The organization permission this setup mutation requires.
     */
    abstract protected function permission(): OrganizationPermission;

    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && Gate::forUser($user)->allows(
                $this->permission()->value,
                $organization,
            );
    }

    /**
     * Return the active organization middleware context.
     */
    public function organization(): ?Organization
    {
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization
            ? $organization
            : null;
    }
}
