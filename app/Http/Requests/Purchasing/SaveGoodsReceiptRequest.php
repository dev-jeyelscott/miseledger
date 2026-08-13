<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\OrganizationPermission;
use App\Models\GoodsReceipt;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveGoodsReceiptRequest extends FormRequest
{
    /**
     * Require receiving permission and tenant-safe route records.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (
            ! $user instanceof User
            || $organization === null
            || ! Gate::forUser($user)->allows(
                OrganizationPermission::ReceivingFinalize->value,
                $organization,
            )
        ) {
            return false;
        }

        if ($this->route('goodsReceipt') !== null) {
            return $this->goodsReceipt() !== null;
        }

        return $this->purchaseOrder() !== null;
    }

    /**
     * Validate one goods receipt draft.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->organization()->id;

        $purchaseOrder = $this->purchaseOrder();
        $purchaseOrderId = (int) $purchaseOrder->id;
        $locationId = (int) $purchaseOrder->location_id;

        return [
            'number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('goods_receipts', 'number')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($this->goodsReceipt()),
            ],
            'supplier_reference' => [
                'nullable',
                'string',
                'max:120',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'lines' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],
            'lines.*.purchase_order_line_id' => [
                'required',
                'integer',
                Rule::exists('purchase_order_lines', 'id')->where(
                    fn (Builder $query): Builder => $query->where(
                        'purchase_order_id',
                        $purchaseOrderId,
                    ),
                ),
            ],
            'lines.*.storage_location_id' => [
                'nullable',
                'integer',
                Rule::exists('storage_locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('location_id', $locationId)
                        ->where('active', true),
                ),
            ],
            'lines.*.received_quantity' => [
                'required',
                'numeric',
                'gte:0',
                'decimal:0,6',
                'max:999999999.999999',
            ],
            'lines.*.received_unit_of_measure_id' => [
                'nullable',
                'integer',
                Rule::exists('units_of_measure', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'lines.*.rejected_quantity' => [
                'nullable',
                'numeric',
                'gte:0',
                'decimal:0,6',
                'max:999999999.999999',
            ],
            'lines.*.rejected_unit_of_measure_id' => [
                'nullable',
                'integer',
                Rule::exists('units_of_measure', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'lines.*.damaged_quantity' => [
                'nullable',
                'numeric',
                'gte:0',
                'decimal:0,6',
                'max:999999999.999999',
            ],
            'lines.*.damaged_unit_of_measure_id' => [
                'nullable',
                'integer',
                Rule::exists('units_of_measure', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
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
     * Resolve the tenant-owned receipt for updates.
     */
    public function goodsReceipt(): ?GoodsReceipt
    {
        $organization = $this->organization();
        $routeId = $this->route('goodsReceipt');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return GoodsReceipt::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }

    /**
     * Resolve the PO from either nested creation or an existing receipt.
     */
    public function purchaseOrder(): ?PurchaseOrder
    {
        $organization = $this->organization();

        if ($organization === null) {
            return null;
        }

        $routeId = $this->route('purchaseOrder');

        if ($routeId !== null && is_numeric($routeId)) {
            return PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->find((int) $routeId);
        }

        $receipt = $this->goodsReceipt();

        if ($receipt === null) {
            return null;
        }

        return PurchaseOrder::query()
            ->where('organization_id', $organization->id)
            ->find($receipt->purchase_order_id);
    }

    /**
     * Normalize document identifiers, optional text, and quantities.
     */
    protected function prepareForValidation(): void
    {
        $number = $this->input('number');
        $supplierReference = $this->input('supplier_reference');
        $notes = $this->input('notes');
        $lines = $this->input('lines');

        if (is_array($lines)) {
            $lines = array_map(
                static function (mixed $line): mixed {
                    if (! is_array($line)) {
                        return $line;
                    }

                    foreach (
                        [
                            'received_quantity',
                            'rejected_quantity',
                            'damaged_quantity',
                        ] as $field
                    ) {
                        $quantity = $line[$field] ?? null;

                        if (is_string($quantity)) {
                            $quantity = trim($quantity);
                        }

                        if (
                            $field !== 'received_quantity'
                            && ($quantity === null || $quantity === '')
                        ) {
                            $quantity = '0';
                        }

                        $line[$field] = $quantity;
                    }

                    $lineNotes = $line['notes'] ?? null;
                    $line['notes'] = is_string($lineNotes)
                        && trim($lineNotes) !== ''
                            ? trim($lineNotes)
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
            'supplier_reference' => is_string($supplierReference)
                && trim($supplierReference) !== ''
                    ? trim($supplierReference)
                    : null,
            'notes' => is_string($notes) && trim($notes) !== ''
                ? trim($notes)
                : null,
            'lines' => $lines,
        ]);
    }
}
