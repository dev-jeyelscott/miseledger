<?php

namespace App\Support\Purchasing;

use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;

final class SupplierIndexQuery
{
    /**
     * @return Builder<Supplier>
     */
    public function builder(
        Organization $organization,
        ?string $search,
        ?string $status,
        string $sort,
    ): Builder {
        $query = Supplier::query()
            ->select('suppliers.*')
            ->where('organization_id', $organization->id)
            ->addSelect([
                'last_purchase_order_number' => PurchaseOrder::query()
                    ->select('number')
                    ->whereColumn('purchase_orders.supplier_id', 'suppliers.id')
                    ->whereColumn('purchase_orders.organization_id', 'suppliers.organization_id')
                    ->orderByDesc('order_date')
                    ->orderByDesc('id')
                    ->limit(1),
                'last_purchase_order_date' => PurchaseOrder::query()
                    ->select('order_date')
                    ->whereColumn('purchase_orders.supplier_id', 'suppliers.id')
                    ->whereColumn('purchase_orders.organization_id', 'suppliers.organization_id')
                    ->orderByDesc('order_date')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->withCount(['supplierItems as item_count']);

        if ($search !== null) {
            $searchPattern = "%{$search}%";

            $query->where(function (Builder $searchQuery) use ($searchPattern): void {
                $searchQuery
                    ->whereLike('name', $searchPattern)
                    ->orWhereLike('code', $searchPattern)
                    ->orWhereLike('contact_name', $searchPattern)
                    ->orWhereLike('email', $searchPattern)
                    ->orWhereLike('phone', $searchPattern);
            });
        }

        if ($status !== null) {
            $query->where('active', $status === 'active');
        }

        match ($sort) {
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'code_asc' => $query->orderBy('code')->orderBy('id'),
            'code_desc' => $query->orderByDesc('code')->orderByDesc('id'),
            'items_desc' => $query->orderByDesc('item_count')->orderBy('name')->orderBy('id'),
            default => $query->orderBy('name')->orderBy('id'),
        };

        return $query;
    }
}
