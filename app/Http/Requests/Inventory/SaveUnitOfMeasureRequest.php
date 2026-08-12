<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Inventory\StandardUnits;
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
            'dimension' => [
                'required',
                'string',
                Rule::in(StandardUnits::dimensions()),
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
     *
     * Missing dimension falls back conservatively for backward-compatible
     * callers. Standard symbols derive their authoritative dimension;
     * otherwise the safe legacy default is count.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $symbol = $this->input('symbol');
        $dimension = $this->input('dimension');

        $normalizedSymbol = is_string($symbol)
            ? trim($symbol)
            : $symbol;

        if (! is_string($dimension) || trim($dimension) === '') {
            $dimension = is_string($normalizedSymbol)
                ? StandardUnits::dimensionFor($normalizedSymbol) ?? 'count'
                : 'count';
        }

        $this->merge([
            'name' => is_string($name)
                ? Str::squish($name)
                : $name,
            'symbol' => $normalizedSymbol,
            'dimension' => is_string($dimension)
                ? strtolower(trim($dimension))
                : $dimension,
            'active' => $this->boolean('active'),
        ]);
    }
}
