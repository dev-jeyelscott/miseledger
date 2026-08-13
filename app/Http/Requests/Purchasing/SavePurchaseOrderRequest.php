<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SavePurchaseOrderRequest extends FormRequest
{
    /**
     * Require purchasing management permission and a tenant-safe PO target.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();

        if (
            ! $user instanceof User
            || $organization === null
            || ! Gate::forUser($user)->allows(
                OrganizationPermission::PurchasingManage->value,
                $organization,
            )
        ) {
            return false;
        }

        return $this->route('purchaseOrder') === null
            || $this->purchaseOrder() !== null;
    }

    /**
     * Validate PO header and line input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = (int) (
            $this->organization()?->id ?? 0
        );

        $supplierId = (int) $this->input('supplier_id', 0);

        return [
            'number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('purchase_orders', 'number')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'organization_id',
                            $organizationId,
                        ),
                    )
                    ->ignore($this->purchaseOrder()),
            ],
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('active', true),
                ),
            ],
            'order_date' => [
                'required',
                'date',
            ],
            'expected_delivery_date' => [
                'nullable',
                'date',
                'after_or_equal:order_date',
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
            'lines.*.supplier_item_id' => [
                'required',
                'integer',
                Rule::exists('supplier_items', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('organization_id', $organizationId)
                        ->where('supplier_id', $supplierId)
                        ->where('active', true),
                ),
            ],
            'lines.*.ordered_quantity' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,6',
                'max:999999999.999999',
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
     * Resolve an existing PO only inside the active organization.
     */
    public function purchaseOrder(): ?PurchaseOrder
    {
        $organization = $this->organization();
        $routeId = $this->route('purchaseOrder');

        if (
            $organization === null
            || $routeId === null
            || ! is_numeric($routeId)
        ) {
            return null;
        }

        return PurchaseOrder::query()
            ->where('organization_id', $organization->id)
            ->find((int) $routeId);
    }

    /**
     * Normalize document identity and decimal strings.
     */
    protected function prepareForValidation(): void
    {
        $number = $this->input('number');
        $notes = $this->input('notes');
        $expected = $this->input('expected_delivery_date');
        $lines = $this->input('lines');

        if (is_array($lines)) {
            $lines = array_map(
                static function (mixed $line): mixed {
                    if (! is_array($line)) {
                        return $line;
                    }

                    $quantity = $line['ordered_quantity'] ?? null;

                    if (is_string($quantity)) {
                        $line['ordered_quantity'] = trim($quantity);
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
            'notes' => is_string($notes) && trim($notes) !== ''
                ? trim($notes)
                : null,
            'expected_delivery_date' => $expected === ''
                ? null
                : $expected,
            'lines' => $lines,
        ]);
    }
}
