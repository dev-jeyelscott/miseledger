<?php

namespace App\Http\Requests\Mobile;

use App\Enums\OrganizationPermission;
use App\Enums\StockCountStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveStockCountLineRequest extends FormRequest
{
    /**
     * Server-authoritative bound mirrored client-side for instant feedback,
     * matching `SaveStockCount::MAX_QUANTITY`.
     */
    private const MAX_QUANTITY = '999999999.999999';

    /**
     * Require physical-count creation permission and an active tenant.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        return $user instanceof User
            && $organization !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::CountsCreate->value,
                $organization,
            );
    }

    /**
     * Validate one scanned physical-count line.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;

        return [
            'stock_count_id' => [
                'nullable',
                'integer',
                Rule::exists('stock_counts', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('status', StockCountStatus::Draft->value),
                ),
            ],
            'storage_location_id' => [
                'required_without:stock_count_id',
                'nullable',
                'integer',
                Rule::exists('storage_locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units_of_measure', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'quantity' => [
                'required',
                'numeric',
                'gte:0',
                'decimal:0,6',
                'max:'.self::MAX_QUANTITY,
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
}
