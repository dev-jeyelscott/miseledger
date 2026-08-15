<?php

namespace App\Http\Requests\Inventory;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReceiveStockTransferRequest extends FormRequest
{
    /**
     * Require receive permission and a tenant-owned transfer target.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->organization();
        $stockTransfer = $this->stockTransfer();

        return $user instanceof User
            && $organization !== null
            && $stockTransfer !== null
            && Gate::forUser($user)->allows(
                OrganizationPermission::TransfersReceive->value,
                $organization,
            );
    }

    /**
     * Validate actual receipt quantities against transfer-owned lines.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $stockTransferId =
            $this->stockTransfer()?->id ?? 0;

        return [
            'lines' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],
            'lines.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(
                    'stock_transfer_lines',
                    'id',
                )->where(
                    fn (Builder $query): Builder => $query->where(
                        'stock_transfer_id',
                        $stockTransferId,
                    ),
                ),
            ],
            'lines.*.received_base_quantity' => [
                'required',
                'numeric',
                'gte:0',
                'decimal:0,6',
                'max:999999999.999999',
            ],
        ];
    }

    /**
     * Return active tenant context.
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
     * Resolve the requested transfer only inside the active tenant.
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
     * Normalize fixed-precision received quantities.
     */
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $this->merge([
            'lines' => array_map(
                static function (mixed $line): mixed {
                    if (! is_array($line)) {
                        return $line;
                    }

                    $quantity =
                        $line['received_base_quantity']
                        ?? null;

                    if (is_string($quantity)) {
                        $line['received_base_quantity'] =
                            trim($quantity);
                    }

                    return $line;
                },
                $lines,
            ),
        ]);
    }
}
