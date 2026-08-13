<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveStockCountRequest extends FormRequest
{
    /**
     * Require count permission and a tenant-owned update target.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (
            ! $user instanceof User
            || $organization === null
            || ! Gate::forUser($user)->allows(
                OrganizationPermission::CountsCreate->value,
                $organization,
            )
        ) {
            return false;
        }

        return $this->route('stockCount') === null
            || $this->stockCount() !== null;
    }

    /**
     * Validate tenant-safe physical-count input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;
        $locationId = (int) $this->input(
            'location_id',
            0,
        );

        return [
            'number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('stock_counts', 'number')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($this->stockCount()),
            ],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where(
                            'organization_id',
                            $organizationId,
                        )
                        ->where('active', true),
                ),
            ],
            'storage_location_id' => [
                'required',
                'integer',
                Rule::exists(
                    'storage_locations',
                    'id',
                )->where(
                    fn (Builder $query): Builder => $query
                        ->where(
                            'organization_id',
                            $organizationId,
                        )
                        ->where(
                            'location_id',
                            $locationId,
                        )
                        ->where('active', true),
                ),
            ],
            'lines' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],
            'lines.*.inventory_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(
                    'inventory_items',
                    'id',
                )->where(
                    fn (Builder $query): Builder => $query
                        ->where(
                            'organization_id',
                            $organizationId,
                        )
                        ->where('active', true),
                ),
            ],
            'lines.*.counted_quantity' => [
                'required',
                'numeric',
                'gte:0',
                'decimal:0,6',
                'max:999999999.999999',
            ],
            'lines.*.count_unit_id' => [
                'required',
                'integer',
                Rule::exists(
                    'units_of_measure',
                    'id',
                )->where(
                    fn (Builder $query): Builder => $query
                        ->where(
                            'organization_id',
                            $organizationId,
                        )
                        ->where('active', true),
                ),
            ],
            'lines.*.notes' => [
                'nullable',
                'string',
                'max:2000',
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
     * Resolve an existing count strictly inside the active tenant.
     */
    public function stockCount(): ?StockCount
    {
        $organization = $this->organization();
        $routeId = $this->route('stockCount');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return StockCount::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->find((int) $routeId);
    }

    /**
     * Normalize count identity, decimal input, and line notes.
     */
    protected function prepareForValidation(): void
    {
        $number = $this->input('number');
        $lines = $this->input('lines');

        if (is_array($lines)) {
            $lines = array_map(
                static function (mixed $line): mixed {
                    if (! is_array($line)) {
                        return $line;
                    }

                    $quantity =
                        $line['counted_quantity'] ?? null;

                    if (is_string($quantity)) {
                        $line['counted_quantity'] =
                            trim($quantity);
                    }

                    $notes = $line['notes'] ?? null;

                    $line['notes'] =
                        is_string($notes)
                        && trim($notes) !== ''
                            ? trim($notes)
                            : null;

                    return $line;
                },
                $lines,
            );
        }

        $this->merge([
            'number' => is_string($number)
                ? Str::upper(trim($number))
                : $number,
            'lines' => $lines,
        ]);
    }
}
