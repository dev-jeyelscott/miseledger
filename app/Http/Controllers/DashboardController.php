<?php

namespace App\Http\Controllers;

use App\Enums\GoodsReceiptStatus;
use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockCountStatus;
use App\Models\GoodsReceipt;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Render a bounded, permission-aware operational summary for the active organization.
     */
    public function index(Request $request): Response
    {
        $organization = $request->attributes->get('activeOrganization');

        if (! $organization instanceof Organization) {
            return Inertia::render('dashboard', [
                'dashboard' => null,
            ]);
        }

        return Inertia::render('dashboard', [
            'dashboard' => $this->dashboardData($request, $organization),
        ]);
    }

    /**
     * Build only the dashboard data that the current user is authorized to inspect.
     *
     * @return array<string, mixed>
     */
    private function dashboardData(
        Request $request,
        Organization $organization,
    ): array {
        $canViewReports = $this->allows(
            $organization,
            OrganizationPermission::ReportsView,
        );
        $canViewCosts = $this->allows(
            $organization,
            OrganizationPermission::CostsView,
        );
        $canViewPurchasing = $this->allows(
            $organization,
            OrganizationPermission::PurchasingView,
        );
        $canManagePurchasing = $this->allows(
            $organization,
            OrganizationPermission::PurchasingManage,
        );
        $canFinalizeReceiving = $this->allows(
            $organization,
            OrganizationPermission::ReceivingFinalize,
        );
        $canCreateCounts = $this->allows(
            $organization,
            OrganizationPermission::CountsCreate,
        );
        $canFinalizeCounts = $this->allows(
            $organization,
            OrganizationPermission::CountsFinalize,
        );
        $canManageOrganization = $this->allows(
            $organization,
            OrganizationPermission::OrganizationManage,
        );
        $canReadCounts = $canViewReports || $canCreateCounts || $canFinalizeCounts;

        $purchaseOrderStats = ($canViewPurchasing || $canManagePurchasing)
            ? $this->purchaseOrderStats($organization)
            : null;

        $stockCountStats = $canReadCounts
            ? $this->stockCountStats($organization)
            : null;

        return [
            'currency' => $organization->currency,
            'timezone' => $organization->timezone,
            'organizationSettings' => $canManageOrganization
                ? [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'timezone' => $organization->timezone,
                    'currency' => $organization->currency,
                    'active' => $organization->active,
                ]
                : null,
            'metrics' => [
                'inventoryValue' => $canViewReports && $canViewCosts
                    ? $this->inventoryValue($organization)
                    : null,
                'lowStockItems' => $canViewReports
                    ? $this->lowStockItemCount($organization)
                    : null,
                'openPurchaseOrders' => $canViewPurchasing
                    ? ($purchaseOrderStats['open'] ?? 0)
                    : null,
                'pendingReceiving' => $canViewPurchasing
                    ? ($purchaseOrderStats['receivable'] ?? 0)
                    : null,
                'openStockCounts' => $canReadCounts
                    ? ($stockCountStats['open'] ?? 0)
                    : null,
            ],
            'organizationStats' => $this->organizationStats($request),
            'lowStockAlerts' => $canViewReports
                ? $this->lowStockAlerts($organization)
                : [],
            'recentActivity' => $canViewReports
                ? $this->recentActivity($organization, $canViewCosts)
                : [],
            'pendingTasks' => [
                'purchaseOrdersAwaitingApproval' => $canManagePurchasing
                    ? ($purchaseOrderStats['draft'] ?? 0)
                    : null,
                'receiptsAwaitingFinalization' => $canFinalizeReceiving
                    ? $this->draftReceiptCount($organization)
                    : null,
                'stockCountsAwaitingFinalization' => $canFinalizeCounts
                    ? ($stockCountStats['submitted'] ?? 0)
                    : null,
            ],
        ];
    }

    /**
     * Check one organization-scoped permission using the application's existing gate.
     */
    private function allows(
        Organization $organization,
        OrganizationPermission $permission,
    ): bool {
        return Gate::allows($permission->value, $organization);
    }

    /**
     * Sum the materialized inventory projection without converting money to floats.
     */
    private function inventoryValue(Organization $organization): string
    {
        $total = StockBalance::query()
            ->where('organization_id', $organization->id)
            ->sum('inventory_value');

        return BigDecimal::of((string) $total)
            ->toScale(4)
            ->__toString();
    }

    /**
     * Count distinct items that currently have at least one zero-or-negative balance.
     */
    private function lowStockItemCount(Organization $organization): int
    {
        return StockBalance::query()
            ->where('organization_id', $organization->id)
            ->where('quantity_on_hand', '<=', '0')
            ->distinct()
            ->count('inventory_item_id');
    }

    /**
     * Return a small low-stock work list using the same zero-or-negative rule as the report.
     *
     * @return list<array<string, int|string>>
     */
    private function lowStockAlerts(Organization $organization): array
    {
        $alerts = StockBalance::query()
            ->select([
                'id',
                'organization_id',
                'location_id',
                'storage_location_id',
                'inventory_item_id',
                'quantity_on_hand',
            ])
            ->with([
                'location:id,name',
                'storageLocation:id,name',
                'inventoryItem:id,name,sku,base_unit_of_measure_id',
                'inventoryItem.baseUnitOfMeasure:id,symbol',
            ])
            ->where('organization_id', $organization->id)
            ->where('quantity_on_hand', '<=', '0')
            ->orderBy('quantity_on_hand')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(static fn (StockBalance $balance): array => [
                'id' => $balance->id,
                'itemId' => $balance->inventory_item_id,
                'itemName' => $balance->inventoryItem->name,
                'sku' => $balance->inventoryItem->sku,
                'locationName' => $balance->location->name,
                'storageLocationName' => $balance->storageLocation->name,
                'quantityOnHand' => $balance->quantity_on_hand,
                'unitSymbol' => $balance
                    ->inventoryItem
                    ->baseUnitOfMeasure
                    ->symbol,
            ])
            ->all();

        return array_values($alerts);
    }

    /**
     * Return the latest immutable stock-ledger evidence for operational awareness.
     *
     * @return list<array<string, int|string|null>>
     */
    private function recentActivity(
        Organization $organization,
        bool $canViewCosts,
    ): array {
        $columns = [
            'id',
            'organization_id',
            'location_id',
            'inventory_item_id',
            'type',
            'quantity',
            'base_unit_of_measure_id',
            'occurred_at',
            'created_by',
        ];

        if ($canViewCosts) {
            $columns[] = 'total_cost';
        }

        $activity = StockMovement::query()
            ->select($columns)
            ->with([
                'location:id,name',
                'inventoryItem:id,name,sku',
                'baseUnitOfMeasure:id,symbol',
                'creator:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(static fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->type->value,
                'itemName' => $movement->inventoryItem->name,
                'sku' => $movement->inventoryItem->sku,
                'locationName' => $movement->location->name,
                'quantity' => $movement->quantity,
                'unitSymbol' => $movement->baseUnitOfMeasure->symbol,
                'totalCost' => $canViewCosts
                    ? $movement->total_cost
                    : null,
                'actorName' => $movement->creator?->name,
                'occurredAt' => $movement->occurred_at->toIso8601String(),
            ])
            ->all();

        return array_values($activity);
    }

    /**
     * Count locations and members only for organizations visible to the user.
     *
     * @return list<array{organizationId: int, locationCount: int, memberCount: int}>
     */
    private function organizationStats(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [];
        }

        $organizationIds = $user->organizationMemberships()
            ->pluck('organization_id');

        $stats = Organization::query()
            ->select('id')
            ->withCount([
                'locations',
                'memberships',
            ])
            ->where('active', true)
            ->whereIn('id', $organizationIds)
            ->get()
            ->map(static fn (Organization $organization): array => [
                'organizationId' => $organization->id,
                'locationCount' => (int) $organization->getAttribute(
                    'locations_count',
                ),
                'memberCount' => (int) $organization->getAttribute(
                    'memberships_count',
                ),
            ])
            ->all();

        return array_values($stats);
    }

    /**
     * Compute purchase-order dashboard counts in one tenant-scoped aggregate query.
     *
     * @return array{open: int, receivable: int, draft: int}
     */
    private function purchaseOrderStats(Organization $organization): array
    {
        $stats = PurchaseOrder::query()
            ->where('organization_id', $organization->id)
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) AS open_count, '
                .'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS receivable_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft_count',
                [
                    PurchaseOrderStatus::Draft->value,
                    PurchaseOrderStatus::Approved->value,
                    PurchaseOrderStatus::PartiallyReceived->value,
                    PurchaseOrderStatus::Approved->value,
                    PurchaseOrderStatus::PartiallyReceived->value,
                    PurchaseOrderStatus::Draft->value,
                ],
            )
            ->first();

        return [
            'open' => (int) ($stats?->getAttribute('open_count') ?? 0),
            'receivable' => (int) (
                $stats?->getAttribute('receivable_count') ?? 0
            ),
            'draft' => (int) ($stats?->getAttribute('draft_count') ?? 0),
        ];
    }

    /**
     * Compute active stock-count dashboard counts in one tenant-scoped aggregate query.
     *
     * @return array{open: int, submitted: int}
     */
    private function stockCountStats(Organization $organization): array
    {
        $stats = StockCount::query()
            ->where('organization_id', $organization->id)
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS open_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS submitted_count',
                [
                    StockCountStatus::Draft->value,
                    StockCountStatus::Submitted->value,
                    StockCountStatus::Submitted->value,
                ],
            )
            ->first();

        return [
            'open' => (int) ($stats?->getAttribute('open_count') ?? 0),
            'submitted' => (int) (
                $stats?->getAttribute('submitted_count') ?? 0
            ),
        ];
    }

    /**
     * Count draft receipts that still require explicit finalization.
     */
    private function draftReceiptCount(Organization $organization): int
    {
        return GoodsReceipt::query()
            ->where('organization_id', $organization->id)
            ->where('status', GoodsReceiptStatus::Draft->value)
            ->count();
    }
}
