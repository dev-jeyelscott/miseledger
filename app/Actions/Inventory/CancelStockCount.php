<?php

namespace App\Actions\Inventory;

use App\Enums\OrganizationPermission;
use App\Enums\StockCountStatus;
use App\Models\Organization;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelStockCount
{
    /**
     * Cancel an inventory-neutral draft or submitted stock count.
     */
    public function handle(
        Organization $organization,
        User $actor,
        StockCount $stockCount,
    ): StockCount {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $stockCount,
        ): StockCount {
            $this->authorize($organization, $actor);

            $count = StockCount::query()
                ->where('organization_id', $organization->id)
                ->whereKey($stockCount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($count->status === StockCountStatus::Cancelled) {
                return $count->refresh();
            }

            if (! $count->status->canCancel()) {
                throw ValidationException::withMessages([
                    'stock_count' => __(
                        'A finalized stock count cannot be cancelled.',
                    ),
                ]);
            }

            $count->forceFill([
                'status' => StockCountStatus::Cancelled,
            ])->save();

            return $count->refresh();
        }, 3);
    }

    /**
     * Require physical-count creation permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::CountsCreate,
            )
        ) {
            abort(403);
        }
    }
}
