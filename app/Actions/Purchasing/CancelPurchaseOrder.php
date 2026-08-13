<?php

namespace App\Actions\Purchasing;

use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelPurchaseOrder
{
    /**
     * Cancel an unreceived PO without affecting inventory.
     */
    public function handle(
        Organization $organization,
        User $actor,
        PurchaseOrder $purchaseOrder,
    ): PurchaseOrder {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $purchaseOrder,
        ): PurchaseOrder {
            if (
                ! $actor->hasOrganizationPermission(
                    $organization,
                    OrganizationPermission::PurchasingManage,
                )
            ) {
                abort(403);
            }

            $record = PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($record->status === PurchaseOrderStatus::Cancelled) {
                return $record;
            }

            if (
                ! in_array(
                    $record->status,
                    [
                        PurchaseOrderStatus::Draft,
                        PurchaseOrderStatus::Approved,
                    ],
                    true,
                )
            ) {
                throw ValidationException::withMessages([
                    'purchase_order' => __(
                        'A partially or fully received purchase order cannot be cancelled.',
                    ),
                ]);
            }

            $hasActiveReceipt = $record
                ->goodsReceipts()
                ->where(
                    'status',
                    '!=',
                    GoodsReceiptStatus::Cancelled->value,
                )
                ->exists();

            if ($hasActiveReceipt) {
                throw ValidationException::withMessages([
                    'purchase_order' => __(
                        'Cancel or resolve existing goods receipts before cancelling this purchase order.',
                    ),
                ]);
            }

            $record->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
            ])->save();

            return $record->refresh();
        }, 3);
    }
}
