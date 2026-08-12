<?php

namespace App\Actions\Suppliers;

use App\Models\Organization;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

final class SaveSupplier
{
    /**
     * Create or update an organization-owned supplier atomically.
     *
     * @param  array{
     *     name: string,
     *     code: string,
     *     contact_name: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     payment_terms: string|null,
     *     lead_time_days: int|null,
     *     active: bool
     * }  $attributes
     */
    public function handle(
        Organization $organization,
        array $attributes,
        ?Supplier $supplier = null,
    ): Supplier {
        return DB::transaction(function () use (
            $organization,
            $attributes,
            $supplier,
        ): Supplier {
            if ($supplier === null) {
                return $organization->suppliers()->create($attributes);
            }

            $lockedSupplier = Supplier::query()
                ->where('organization_id', $organization->getKey())
                ->whereKey($supplier->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSupplier->update($attributes);

            return $lockedSupplier;
        });
    }
}
