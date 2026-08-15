<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveStockTransferRequest extends FormRequest
{
    /**
     * Require transfer creation permission and a tenant-owned update target.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (
            ! $user instanceof User
            || $organization === null
            || ! Gate::forUser($user)->allows(
                OrganizationPermission::TransfersCreate->value,
                $organization,
            )
        ) {
            return false;
        }

        return $this->route('stockTransfer') === null
            || $this->stockTransfer() !== null;
    }

    /**
     * Validate tenant-safe transfer draft input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;
        $fromLocationId = (int) $this->input(
            'from_location_id',
            0,
        );
        $toLocationId = (int) $this->input(
            'to_location_id',
            0,
        );

        return [
            'number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('stock_transfers', 'number')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($this->stockTransfer()),
            ],
            'from_location_id' => [
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
            'from_storage_location_id' => [
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
                            $fromLocationId,
                        )
                        ->where('active', true),
                ),
            ],
            'to_location_id' => [
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
            'to_storage_location_id' => [
                'required',
                'integer',
                'different:from_storage_location_id',
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
                            $toLocationId,
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
            'lines.*.requested_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,6',
                'max:999999999.999999',
            ],
            'lines.*.unit_id' => [
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
            'notes' => [
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
     * Resolve an existing transfer strictly inside the active tenant.
     */
    public function stockTransfer(): ?StockTransfer
    {
        $organization = $this->organization();
        $routeId = $this->route('stockTransfer');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return StockTransfer::query()
            ->where(
                'organization_id',
                $organization->id,
            )
            ->find((int) $routeId);
    }

    /**
     * Normalize transfer identity, quantities, and optional notes.
     */
    protected function prepareForValidation(): void
    {
        $number = $this->input('number');
        $notes = $this->input('notes');
        $lines = $this->input('lines');

        if (is_array($lines)) {
            $lines = array_map(
                static function (mixed $line): mixed {
                    if (! is_array($line)) {
                        return $line;
                    }

                    $quantity =
                        $line['requested_quantity'] ?? null;

                    if (is_string($quantity)) {
                        $line['requested_quantity'] =
                            trim($quantity);
                    }

                    return $line;
                },
                $lines,
            );
        }

        $this->merge([
            'number' => is_string($number)
                ? Str::upper(trim($number))
                : $number,
            'notes' => is_string($notes)
                && trim($notes) !== ''
                    ? trim($notes)
                    : null,
            'lines' => $lines,
        ]);
    }
}
