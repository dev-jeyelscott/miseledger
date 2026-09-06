<?php

namespace App\Support\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class GoodsReceiptIndexQuery
{
    /**
     * @param  array{search: string|null, status: string|null, supplierId: int|null, locationId: int|null, from: string|null, to: string|null, sort: string}  $filters
     * @return Builder<GoodsReceipt>
     */
    public function builder(Organization $organization, array $filters): Builder
    {
        $query = GoodsReceipt::query()
            ->where('organization_id', $organization->id);

        if ($filters['search'] !== null) {
            $searchPattern = "%{$filters['search']}%";

            $query->where(function (Builder $searchQuery) use ($searchPattern): void {
                $searchQuery
                    ->whereLike('number', $searchPattern)
                    ->orWhereHas('purchaseOrder', fn (Builder $purchaseOrderQuery): Builder => $purchaseOrderQuery->whereLike('number', $searchPattern))
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
            $query->where('received_at', '>=', CarbonImmutable::parse($filters['from'], $organization->timezone)->startOfDay()->utc());
        }

        if ($filters['to'] !== null) {
            $query->where('received_at', '<=', CarbonImmutable::parse($filters['to'], $organization->timezone)->endOfDay()->utc());
        }

        return $query;
    }
}
