<?php

namespace App\Support\Mobile;

use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockCountStatus;
use App\Enums\StockTransferStatus;
use App\Models\Location;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Inventory\StockBalanceReportQuery;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The single place that aggregates operational work across four
 * authoritative tables into a shared, permission-filtered, urgency-sorted
 * `MobileTask` list (Spec 7). No task state is persisted: every call reads
 * live from `PurchaseOrder`, `StockCount`, `StockTransfer`, and
 * `StockBalance`.
 *
 * Urgency band rule (canonical definition, also referenced by Specs 3-6):
 * - `overdue`: a PO past its `expected_delivery_date`, or a transfer
 *   `Shipped` more than 24 hours ago waiting to be received.
 * - `in_progress`: a Count `Submitted`, or a PO `PartiallyReceived`.
 * - `ready`: a Count `Draft`, a PO `Approved`, or a Transfer `Draft`/
 *   `Shipped` within the last 24 hours.
 * - `attention`: a Restock task (an item at zero/negative `StockBalance`).
 *
 * Within a band, the oldest task sorts first.
 */
final class MobileTaskAggregator
{
    /**
     * Per-type query cap, high enough for any real tenant's open work while
     * bounding a single request's worst-case query cost.
     */
    private const int PER_TYPE_LIMIT = 100;

    /**
     * @var array<string, int>
     */
    private const array URGENCY_RANK = [
        'overdue' => 0,
        'in_progress' => 1,
        'ready' => 2,
        'attention' => 3,
    ];

    public function __construct(
        private readonly StockBalanceReportQuery $stockBalanceReportQuery,
    ) {}

    /**
     * Build the full, permission-filtered, urgency-sorted task list for one
     * location. Each source query runs only when the actor already holds
     * the matching permission, so an under-permissioned actor never causes
     * a query it has no right to see the shape of.
     *
     * @return Collection<int, MobileTask>
     */
    public function forLocation(Organization $organization, Location $location, User $actor): Collection
    {
        $tasks = collect();

        if ($actor->hasOrganizationPermission($organization, OrganizationPermission::ReceivingFinalize)) {
            $tasks = $tasks->merge($this->receiveTasks($organization, $location));
        }

        if ($actor->hasOrganizationPermission($organization, OrganizationPermission::CountsCreate)) {
            $tasks = $tasks->merge($this->countTasks($organization, $location));
        }

        if ($actor->hasOrganizationPermission($organization, OrganizationPermission::TransfersShip)) {
            $tasks = $tasks->merge($this->shipTasks($organization, $location));
        }

        if ($actor->hasOrganizationPermission($organization, OrganizationPermission::TransfersReceive)) {
            $tasks = $tasks->merge($this->receiveTransferTasks($organization, $location));
        }

        if ($actor->hasOrganizationPermission($organization, OrganizationPermission::InventoryView)) {
            $tasks = $tasks->merge($this->restockTasks($organization, $location));
        }

        return $this->sort($tasks);
    }

    /**
     * @return Collection<int, MobileTask>
     */
    private function receiveTasks(Organization $organization, Location $location): Collection
    {
        $today = now($organization->timezone)->toDateString();

        return PurchaseOrder::query()
            ->with('supplier:id,name')
            ->where('organization_id', $organization->id)
            ->where('location_id', $location->id)
            ->whereIn('status', [
                PurchaseOrderStatus::Approved->value,
                PurchaseOrderStatus::PartiallyReceived->value,
            ])
            ->orderBy('created_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (PurchaseOrder $purchaseOrder) use ($today, $organization): MobileTask {
                $overdue = $purchaseOrder->expected_delivery_date !== null
                    && $purchaseOrder->expected_delivery_date->toDateString() < $today;

                $urgency = match (true) {
                    $overdue => 'overdue',
                    $purchaseOrder->status === PurchaseOrderStatus::PartiallyReceived => 'in_progress',
                    default => 'ready',
                };

                $statusText = match (true) {
                    $overdue => $this->overdueText($purchaseOrder->expected_delivery_date, $organization),
                    $purchaseOrder->status === PurchaseOrderStatus::PartiallyReceived => 'partially received',
                    default => 'ready to receive',
                };

                return new MobileTask(
                    type: 'receive',
                    urgency: $urgency,
                    title: "Receive PO {$purchaseOrder->number}",
                    subtitle: "{$purchaseOrder->supplier->name} — {$statusText}",
                    href: route('mobile.receiving.scan', ['purchase_order_id' => $purchaseOrder->id]),
                    createdAt: $purchaseOrder->created_at->toIso8601String(),
                );
            });
    }

    /**
     * @return Collection<int, MobileTask>
     */
    private function countTasks(Organization $organization, Location $location): Collection
    {
        return StockCount::query()
            ->with('storageLocation:id,name')
            ->where('organization_id', $organization->id)
            ->where('location_id', $location->id)
            ->whereIn('status', [
                StockCountStatus::Draft->value,
                StockCountStatus::Submitted->value,
            ])
            ->orderBy('created_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (StockCount $count): MobileTask {
                $submitted = $count->status === StockCountStatus::Submitted;

                return new MobileTask(
                    type: 'count',
                    urgency: $submitted ? 'in_progress' : 'ready',
                    title: "Count {$count->number}",
                    subtitle: "{$count->storageLocation->name} — ".($submitted ? 'submitted, needs review' : 'in progress'),
                    href: $submitted
                        ? route('mobile.stock-counts.review', $count)
                        : route('mobile.stock-counts.scan', ['stock_count_id' => $count->id]),
                    createdAt: $count->created_at->toIso8601String(),
                );
            });
    }

    /**
     * @return Collection<int, MobileTask>
     */
    private function shipTasks(Organization $organization, Location $location): Collection
    {
        return StockTransfer::query()
            ->with('toLocation:id,name')
            ->where('organization_id', $organization->id)
            ->where('from_location_id', $location->id)
            ->where('status', StockTransferStatus::Draft->value)
            ->orderBy('created_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(fn (StockTransfer $transfer): MobileTask => new MobileTask(
                type: 'ship',
                urgency: 'ready',
                title: "Ship transfer {$transfer->number}",
                subtitle: "To {$transfer->toLocation->name} — ready to ship",
                href: route('mobile.transfers.show', $transfer),
                createdAt: $transfer->created_at->toIso8601String(),
            ));
    }

    /**
     * @return Collection<int, MobileTask>
     */
    private function receiveTransferTasks(Organization $organization, Location $location): Collection
    {
        return StockTransfer::query()
            ->with('fromLocation:id,name')
            ->where('organization_id', $organization->id)
            ->where('to_location_id', $location->id)
            ->where('status', StockTransferStatus::Shipped->value)
            ->orderBy('shipped_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get()
            ->map(function (StockTransfer $transfer): MobileTask {
                $shippedAt = $transfer->shipped_at ?? $transfer->created_at;
                $overdue = $shippedAt->diffInHours(now()) > 24;

                return new MobileTask(
                    type: 'receive_transfer',
                    urgency: $overdue ? 'overdue' : 'ready',
                    title: "Receive transfer {$transfer->number}",
                    subtitle: "From {$transfer->fromLocation->name} — ".($overdue ? 'shipped over 24h ago' : 'shipped, awaiting receipt'),
                    href: route('mobile.transfers.show', $transfer),
                    createdAt: $shippedAt->toIso8601String(),
                );
            });
    }

    /**
     * Restock tasks are grouped one card per item (not one per storage
     * location or unit), per decision #4 / Behavior step 1.
     *
     * @return Collection<int, MobileTask>
     */
    private function restockTasks(Organization $organization, Location $location): Collection
    {
        $balances = $this->stockBalanceReportQuery
            ->lowStock($organization, $location->id, null, null, null, null, null)
            ->limit(self::PER_TYPE_LIMIT)
            ->get();

        return $balances
            ->groupBy('inventory_item_id')
            ->map(function (Collection $itemBalances): MobileTask {
                /** @var StockBalance $first */
                $first = $itemBalances->first();
                $item = $first->inventoryItem;

                $oldestSignal = $itemBalances
                    ->map(fn (StockBalance $balance): ?CarbonImmutable => $balance->last_movement_at ?? $balance->created_at)
                    ->filter()
                    ->sort()
                    ->first() ?? $first->created_at ?? CarbonImmutable::now();

                return new MobileTask(
                    type: 'restock',
                    urgency: 'attention',
                    title: "Restock {$item->name}",
                    subtitle: 'Zero or negative stock on hand',
                    href: route('mobile.scan.index', ['item_id' => $item->id]),
                    createdAt: $oldestSignal->toIso8601String(),
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, MobileTask>  $tasks
     * @return Collection<int, MobileTask>
     */
    private function sort(Collection $tasks): Collection
    {
        return $tasks
            ->sort(function (MobileTask $a, MobileTask $b): int {
                $urgencyComparison = self::URGENCY_RANK[$a->urgency] <=> self::URGENCY_RANK[$b->urgency];

                return $urgencyComparison !== 0 ? $urgencyComparison : $a->createdAt <=> $b->createdAt;
            })
            ->values();
    }

    private function overdueText(CarbonInterface $expectedDeliveryDate, Organization $organization): string
    {
        $days = (int) $expectedDeliveryDate->diffInDays(now($organization->timezone)->startOfDay());

        return match (true) {
            $days <= 0 => 'overdue',
            $days === 1 => '1 day overdue',
            default => "{$days} days overdue",
        };
    }
}
