<?php

namespace App\Actions\Inventory;

use App\Models\Barcode;
use App\Models\Organization;
use App\Support\Inventory\BarcodeLookupResult;

final class LookupBarcode
{
    /**
     * Resolve an exact, active barcode match scoped to the organization.
     */
    public function handle(Organization $organization, string $value): BarcodeLookupResult
    {
        $barcode = Barcode::query()
            ->where('organization_id', $organization->getKey())
            ->where('value', $value)
            ->where('active', true)
            ->with(['inventoryItem', 'inventoryItemUnit.unitOfMeasure'])
            ->first();

        if ($barcode === null) {
            return BarcodeLookupResult::notFound();
        }

        return BarcodeLookupResult::found($barcode);
    }
}
