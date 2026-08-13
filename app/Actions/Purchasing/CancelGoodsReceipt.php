<?php

namespace App\Actions\Purchasing;

use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationPermission;
use App\Models\GoodsReceipt;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelGoodsReceipt
{
    /**
     * Cancel a receipt only while it remains inventory-neutral.
     */
    public function handle(
        Organization $organization,
        User $actor,
        GoodsReceipt $goodsReceipt,
    ): GoodsReceipt {
        return DB::transaction(function () use (
            $organization,
            $actor,
            $goodsReceipt,
        ): GoodsReceipt {
            if (
                ! $actor->hasOrganizationPermission(
                    $organization,
                    OrganizationPermission::ReceivingFinalize,
                )
            ) {
                abort(403);
            }

            $receipt = GoodsReceipt::query()
                ->where('organization_id', $organization->id)
                ->whereKey($goodsReceipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($receipt->status === GoodsReceiptStatus::Cancelled) {
                return $receipt;
            }

            if ($receipt->status !== GoodsReceiptStatus::Draft) {
                throw ValidationException::withMessages([
                    'goods_receipt' => __(
                        'A finalized goods receipt cannot be cancelled.',
                    ),
                ]);
            }

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::Cancelled,
            ])->save();

            return $receipt->refresh();
        }, 3);
    }
}
