<?php

namespace App\Http\Requests\Mobile;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AddReceivingLineRequest extends FormRequest
{
    /**
     * Server-authoritative bound mirrored client-side for instant feedback,
     * matching `SaveGoodsReceipt::MAX_QUANTITY`.
     */
    private const MAX_QUANTITY = '999999999.999999';

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
     * Validate one scanned receiving line.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;

        return [
            'purchase_order_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_orders', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organizationId,
                    ),
                ),
            ],
            'goods_receipt_id' => [
                'nullable',
                'integer',
                Rule::exists('goods_receipts', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'organization_id',
                        $organizationId,
                    ),
                ),
            ],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(
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
                'gt:0',
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
