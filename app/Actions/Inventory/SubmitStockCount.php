<?php

namespace App\Actions\Inventory;

use App\Enums\OrganizationPermission;
use App\Enums\StockCountStatus;
use App\Models\Organization;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitStockCount
{
    /**
     * Freeze draft count evidence before inventory reconciliation.
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

            if ($count->status === StockCountStatus::Submitted) {
                return $count->refresh();
            }

            if ($count->status !== StockCountStatus::Draft) {
                throw ValidationException::withMessages([
                    'stock_count' => __(
                        'Only draft stock counts can be submitted.',
                    ),
                ]);
            }

            if (! $count->lines()->exists()) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'At least one stock-count line is required.',
                    ),
                ]);
            }

            $count->forceFill([
                'status' => StockCountStatus::Submitted,
                'counted_at' => now(),
                'submitted_by' => $actor->id,
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
