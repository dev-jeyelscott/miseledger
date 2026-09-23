<?php

namespace App\Actions\Onboarding;

use App\Models\InventoryItem;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MarkItemWithoutOpeningStock
{
    /**
     * Explicitly resolve an item as starting with no stock on hand.
     *
     * This is a deliberate owner decision and is distinct from an item whose
     * opening stock is still unresolved. No stock movement is written.
     */
    public function handle(
        Organization $organization,
        InventoryItem $inventoryItem,
        User $actor,
    ): InventoryItem {
        return DB::transaction(function () use (
            $organization,
            $inventoryItem,
            $actor,
        ): InventoryItem {
            $lockedItem = InventoryItem::query()
                ->where('organization_id', $organization->getKey())
                ->where('active', true)
                ->whereKey($inventoryItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->stockMovements()->exists()) {
                throw ValidationException::withMessages([
                    'resolution' => __(':item already has recorded stock history.', [
                        'item' => $lockedItem->name,
                    ]),
                ]);
            }

            if ($lockedItem->opening_stock_waived_at === null) {
                $lockedItem->forceFill([
                    'opening_stock_waived_at' => now(),
                    'opening_stock_waived_by' => $actor->getKey(),
                ])->save();
            }

            return $lockedItem;
        });
    }
}
