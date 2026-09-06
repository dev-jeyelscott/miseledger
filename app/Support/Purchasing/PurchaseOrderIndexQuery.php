<?php

namespace App\Support\Purchasing;

use App\Models\Organization;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;

final class PurchaseOrderIndexQuery
{
    /**
     * @param  array{search: string|null, status: string|null, supplierId: int|null, locationId: int|null, from: string|null, to: string|null}  $filters
     * @return Builder<PurchaseOrder>
     */
    public function builder(Organization $organization, array $filters): Builder
    {
        $query = PurchaseOrder::query()
            ->where('organization_id', $organization->id);

        if ($filters['search'] !== null) {
            $searchPattern = "%{$filters['search']}%";

            $query->where(function (Builder $searchQuery) use ($searchPattern): void {
                $searchQuery
                    ->whereLike('number', $searchPattern)
                    ->orWhereHas('supplier', fn (Builder $supplierQuery): Builder => $supplierQuery->whereLike('name', $searchPattern));
            });
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['supplierId'] !== null) {
            $query->where('supplier_id', $filters['supplierId']);
        }

        if ($filters['locationId'] !== null) {
            $query->where('location_id', $filters['locationId']);
        }

        if ($filters['from'] !== null) {
            $query->whereDate('order_date', '>=', $filters['from']);
        }

        if ($filters['to'] !== null) {
            $query->whereDate('order_date', '<=', $filters['to']);
        }

        return $query;
    }
}
