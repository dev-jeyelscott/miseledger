<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveUnitOfMeasureRequest extends FormRequest
{
    /**
     * Require inventory-adjust permission and tenant-safe update targets.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (
            ! $user instanceof User
            || $organization === null
            || ! Gate::forUser($user)->allows(
                OrganizationPermission::InventoryAdjust->value,
                $organization,
            )
        ) {
            return false;
        }

        return $this->route('unitOfMeasure') === null
            || $this->unitOfMeasure() !== null;
    }

    /**
     * Validate a tenant-scoped UOM.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) (
            $this->organization()?->getKey() ?? 0
        );

        $unitOfMeasure = $this->unitOfMeasure();

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('units_of_measure', 'name')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($unitOfMeasure),
            ],
            'symbol' => [
                'required',
                'string',
                'max:32',
                Rule::unique('units_of_measure', 'symbol')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($unitOfMeasure),
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Return the active organization resolved by tenancy middleware.
     */
    public function organization(): ?Organization
    {
        $organization = $this->attributes->get('activeOrganization');

        return $organization instanceof Organization
            ? $organization
            : null;
    }

    /**
     * Resolve an update target strictly inside the active organization.
     */
    public function unitOfMeasure(): ?UnitOfMeasure
    {
        $organization = $this->organization();
        $routeId = $this->route('unitOfMeasure');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return UnitOfMeasure::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }

    /**
     * Normalize reusable master-data values before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $symbol = $this->input('symbol');

        $this->merge([
            'name' => is_string($name)
                ? Str::squish($name)
                : $name,
            'symbol' => is_string($symbol)
                ? trim($symbol)
                : $symbol,
            'active' => $this->boolean('active'),
        ]);
    }
}
