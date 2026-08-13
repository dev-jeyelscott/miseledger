<?php

namespace App\Actions\Purchasing;

use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovePurchaseOrder
{
    /**
     * Approve a draft PO without changing inventory.
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
            $this->authorize($organization, $actor);

            $record = PurchaseOrder::query()
                ->where('organization_id', $organization->id)
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($record->status === PurchaseOrderStatus::Approved) {
                return $record;
            }

            if ($record->status !== PurchaseOrderStatus::Draft) {
                throw ValidationException::withMessages([
                    'purchase_order' => __(
                        'Only a draft purchase order can be approved.',
                    ),
                ]);
            }

            if (! $record->lines()->exists()) {
                throw ValidationException::withMessages([
                    'lines' => __(
                        'A purchase order requires at least one line before approval.',
                    ),
                ]);
            }

            $approvedAt = now();

            $record->forceFill([
                'status' => PurchaseOrderStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => $approvedAt,
            ])->save();

            AuditLog::query()->create([
                'organization_id' => $organization->id,
                'actor_id' => $actor->id,
                'action' => 'purchase_order.approved',
                'entity_type' => 'purchase_order',
                'entity_id' => $record->id,
                'before_data' => [
                    'status' => PurchaseOrderStatus::Draft->value,
                ],
                'after_data' => [
                    'status' => PurchaseOrderStatus::Approved->value,
                    'approved_at' => $approvedAt->toIso8601String(),
                ],
                'correlation_id' => "purchase_order:{$record->id}:approve",
            ]);

            return $record->refresh();
        }, 3);
    }

    /**
     * Require purchasing management permission.
     */
    private function authorize(
        Organization $organization,
        User $actor,
    ): void {
        if (
            ! $actor->hasOrganizationPermission(
                $organization,
                OrganizationPermission::PurchasingManage,
            )
        ) {
            abort(403);
        }
    }
}
