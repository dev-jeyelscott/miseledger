<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\OrganizationPermission;
use App\Enums\StockTransferStatus;
use App\Models\Organization;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelStockTransfer
{
    public function __construct(
        private readonly RecordAuditEntry $recordAuditEntry,
    ) {}

    /**
     * Cancel an inventory-neutral transfer draft.
     */
    public function handle(
        Organization $organization,
        User $actor,
        StockTransfer $stockTransfer,
    ): StockTransfer {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $stockTransfer,
        ): StockTransfer {
            $this->authorize(
                $organization,
                $actor,
            );

            $transfer = StockTransfer::query()
                ->where(
                    'organization_id',
                    $organization->id,
                )
                ->whereKey($stockTransfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $transfer->status
                === StockTransferStatus::Cancelled
            ) {
                return $transfer->refresh();
            }

            if (
                $transfer->status
                !== StockTransferStatus::Draft
            ) {
                throw ValidationException::withMessages([
                    'stock_transfer' => __(
                        'Only draft stock transfers can be cancelled.',
                    ),
                ]);
            }

            $transfer->forceFill([
                'status' => StockTransferStatus::Cancelled,
            ])->save();

            $this->recordAuditEntry->handle(
                organization: $organization,
                actor: $actor,
                action: 'stock_transfer.cancelled',
                entityType: 'stock_transfer',
                entityId: $transfer->id,
                beforeData: [
                    'status' => StockTransferStatus::Draft->value,
                ],
                afterData: [
                    'status' => StockTransferStatus::Cancelled->value,
                ],
                correlationId: "stock-transfer:{$transfer->id}:cancel",
            );

            return $transfer->refresh();
        }, 3);
    }

    /**
     * Require transfer creation permission for draft cancellation.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::TransfersCreate,
            )
        ) {
            abort(403);
        }
    }
}
