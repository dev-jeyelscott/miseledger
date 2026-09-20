<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrganizationPermission;
use App\Enums\StockCountStatus;
use App\Models\Organization;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelStockCount
{
    public function __construct(
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

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

            $previousStatus = $count->status;

            $count->forceFill([
                'status' => StockCountStatus::Cancelled,
            ])->save();

            $this->recordAuditEntry->handle(
                organization: $organization,
                actor: $actor,
                action: 'stock_count.cancelled',
                entityType: 'stock_count',
                entityId: $count->id,
                beforeData: [
                    'status' => $previousStatus->value,
                ],
                afterData: [
                    'status' => StockCountStatus::Cancelled->value,
                ],
                correlationId: "stock-count:{$count->id}:cancel",
            );

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
