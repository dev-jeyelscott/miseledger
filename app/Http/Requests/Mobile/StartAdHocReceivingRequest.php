<?php

namespace App\Http\Requests\Mobile;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StartAdHocReceivingRequest extends FormRequest
{
    /**
     * Require receiving permission and an active tenant.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::ReceivingFinalize->value,
                $organization,
            );
    }

    /**
     * Validate the ad-hoc supplier selection.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
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
     * Resolve the validated, tenant-owned supplier.
     */
    public function supplier(): ?Supplier
    {
        $organization = $this->organization();

        if ($organization === null) {
            return null;
        }

        return Supplier::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->find((int) $this->input('supplier_id'));
    }
}
