<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SearchInventoryItems
{
    /**
     * Loose, organization-scoped substring match over name, SKU, and
     * barcode value, for the manual-search fallback and the scan
     * unmatched-barcode fallback (Spec 2 decisions #27 and #32.A).
     *
     * @return Collection<int, InventoryItem>
     */
    public function handle(
        Organization $organization,
        string $query,
        int $limit = 20,
    ): Collection {
        $term = trim($query);

        if ($term === '') {
            return collect();
        }

        return InventoryItem::query()
            ->where('organization_id', $organization->getKey())
            ->where('active', true)
            ->where(function (Builder $builder) use ($term): void {
                $builder
                    ->where('name', 'ILIKE', "%{$term}%")
                    ->orWhere('sku', 'ILIKE', "%{$term}%")
                    ->orWhereHas(
                        'barcodes',
                        function (Builder $barcodeQuery) use ($term): void {
                            $barcodeQuery
                                ->where('barcode', 'ILIKE', "%{$term}%")
                                ->where('active', true);
                        },
                    );
            })
            ->with('baseUnitOfMeasure')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
