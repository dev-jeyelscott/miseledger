<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWasteReasonRequest extends FormRequest
{
    /**
     * Limit waste-reason configuration to inventory operators.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            );
    }

    /**
     * Validate an organization-scoped unique reason name.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('waste_reasons', 'name')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organizationId,
                    ),
                ),
            ],
        ];
    }

    /**
     * Return active organization middleware context.
     */
    public function organization(): ?Organization
    {
        $organization = $this->attributes->get(
            'activeOrganization',
        );

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    /**
     * Normalize the retained business label.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        $this->merge([
            'name' => is_string($name)
                ? trim($name)
                : $name,
        ]);
    }
}
