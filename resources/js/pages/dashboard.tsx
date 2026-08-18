import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Building2,
    ClipboardCheck,
    ClipboardList,
    MapPin,
    Plus,
    ReceiptText,
    Settings,
    ShoppingCart,
    Trash2,
    Truck,
    Users,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';

import LowStockReportController from '@/actions/App/Http/Controllers/Inventory/LowStockReportController';
import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import StockMovementLedgerReportController from '@/actions/App/Http/Controllers/Inventory/StockMovementLedgerReportController';
import WasteController from '@/actions/App/Http/Controllers/Inventory/WasteController';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { OrganizationContext } from '@/types';

type DashboardOrganizationStat = {
    organizationId: number;
    locationCount: number;
    memberCount: number;
};

type DashboardLowStockAlert = {
    id: number;
    itemId: number;
    itemName: string;
    sku: string;
    locationName: string;
    storageLocationName: string;
    quantityOnHand: string;
    unitSymbol: string;
};

type DashboardActivity = {
    id: number;
    type: string;
    itemName: string;
    sku: string;
    locationName: string;
    quantity: string;
    unitSymbol: string;
    totalCost: string | null;
    actorName: string | null;
    occurredAt: string;
};

type DashboardData = {
    currency: string;
    timezone: string;
    metrics: {
        inventoryValue: string | null;
        lowStockItems: number | null;
        openPurchaseOrders: number | null;
        pendingReceiving: number | null;
        openStockCounts: number | null;
    };
    organizationStats: DashboardOrganizationStat[];
    lowStockAlerts: DashboardLowStockAlert[];
    recentActivity: DashboardActivity[];
    pendingTasks: {
        purchaseOrdersAwaitingApproval: number | null;
        receiptsAwaitingFinalization: number | null;
        stockCountsAwaitingFinalization: number | null;
    };
};

type PageProps = {
    organizationContext: OrganizationContext;
    dashboard: DashboardData | null;
};

type PanelHeaderProps = {
    title: string;
    action?: ReactNode;
};

type InertiaLinkHref = ComponentProps<typeof Link>['href'];

type PendingTaskProps = {
    href: InertiaLinkHref;
    icon: LucideIcon;
    label: string;
    count: number;
};

type QuickActionProps = {
    href: InertiaLinkHref;
    icon: LucideIcon;
    label: string;
    description: string;
};

const movementLabels: Record<string, string> = {
    opening_balance: 'Opening balance',
    purchase_receipt: 'Purchase receipt',
    waste: 'Waste recorded',
    transfer_out: 'Transfer out',
    transfer_in: 'Transfer in',
    count_adjustment: 'Count adjustment',
    manual_adjustment: 'Manual adjustment',
};

/** Group integer digits without converting fixed-precision values to floats. */
function groupInteger(value: string): string {
    const isNegative = value.startsWith('-');
    const digits = isNegative ? value.slice(1) : value;
    const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return isNegative ? `-${grouped}` : grouped;
}

/** Format a decimal money string while preserving persisted precision. */
function formatCurrency(value: string, currency: string): string {
    const [integerPart, fractionPart = ''] = value.split('.');
    const trimmedFraction = fractionPart.replace(/0+$/, '');
    const displayFraction = trimmedFraction.padEnd(2, '0');

    return `${currency} ${groupInteger(integerPart)}.${displayFraction}`;
}

/** Format a six-decimal inventory quantity without floating-point conversion. */
function formatQuantity(value: string): string {
    const [integerPart, fractionPart = ''] = value.split('.');
    const trimmedFraction = fractionPart.replace(/0+$/, '');

    return trimmedFraction.length === 0
        ? groupInteger(integerPart)
        : `${groupInteger(integerPart)}.${trimmedFraction}`;
}

/** Render ledger timestamps in the active organization's configured timezone. */
function formatDateTime(value: string, timeZone: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone,
    }).format(new Date(value));
}

/** Keep dashboard panel headings and secondary actions visually consistent. */
function PanelHeader({ title, action }: PanelHeaderProps) {
    return (
        <div className="flex min-h-12 items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 dark:border-sidebar-border">
            <h2 className="text-sm font-semibold">{title}</h2>
            {action}
        </div>
    );
}

/** Render one keyboard-accessible operational task shortcut. */
function PendingTask({ href, icon: Icon, label, count }: PendingTaskProps) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-lg px-2 py-2 text-sm transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <Icon className="size-4 shrink-0 text-muted-foreground" />

            <span className="min-w-0 flex-1 truncate">{label}</span>

            <Badge variant="secondary" className="tabular-nums">
                {count}
            </Badge>
        </Link>
    );
}

/** Render a permission-gated link into an existing inventory workflow. */
function QuickAction({
    href,
    icon: Icon,
    label,
    description,
}: QuickActionProps) {
    return (
        <Link
            href={href}
            className="group flex min-h-24 flex-col justify-between rounded-lg border border-sidebar-border/70 p-3 transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:border-sidebar-border"
        >
            <Icon className="size-5 text-muted-foreground transition-colors group-hover:text-foreground" />

            <div>
                <p className="mt-3 text-sm font-medium">{label}</p>
                <p className="mt-1 text-xs leading-4 text-muted-foreground">
                    {description}
                </p>
            </div>
        </Link>
    );
}

export default function Dashboard() {
    const { organizationContext, dashboard: dashboardData } =
        usePage<PageProps>().props;

    const activeMembership = organizationContext.memberships.find(
        (membership) =>
            membership.organization.id === organizationContext.active?.id,
    );

    const permissions = new Set(activeMembership?.permissions ?? []);

    if (organizationContext.active === null || dashboardData === null) {
        return (
            <>
                <Head title="Dashboard" />

                <div className="flex flex-1 items-start justify-center p-4 sm:p-6">
                    <div className="w-full max-w-xl rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border">
                        <div className="flex size-11 items-center justify-center rounded-full bg-muted">
                            <Building2
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>

                        <h1 className="mt-4 text-2xl font-semibold">
                            Create your organization
                        </h1>

                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            An organization is required before
                            organization-scoped inventory data can be created.
                        </p>

                        <Button className="mt-6" asChild>
                            <Link href={OrganizationController.create()}>
                                <Plus className="size-4" aria-hidden="true" />
                                Create organization
                            </Link>
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    const organizationStats = new Map(
        dashboardData.organizationStats.map((stat) => [
            stat.organizationId,
            stat,
        ]),
    );

    const canViewReports = permissions.has('reports.view');
    const canViewCosts = permissions.has('costs.view');

    const hasPendingTasks = [
        dashboardData.pendingTasks.purchaseOrdersAwaitingApproval,
        dashboardData.pendingTasks.receiptsAwaitingFinalization,
        dashboardData.pendingTasks.stockCountsAwaitingFinalization,
    ].some((count) => count !== null);

    const hasQuickActions =
        permissions.has('purchasing.manage') ||
        permissions.has('receiving.finalize') ||
        permissions.has('counts.create') ||
        permissions.has('waste.record');

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-4 p-4 sm:p-6">
                <section className="flex flex-col justify-between gap-4 rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm lg:flex-row lg:items-center dark:border-sidebar-border">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm text-muted-foreground">
                                Active organization
                            </p>

                            <Badge variant="secondary">Active</Badge>
                        </div>

                        <h1 className="mt-1 truncate text-2xl font-semibold tracking-tight">
                            {organizationContext.active.name}
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground capitalize">
                            Role:{' '}
                            {activeMembership?.role.replaceAll('_', ' ') ??
                                'Unknown'}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {permissions.has('locations.manage') && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={OrganizationLocationController.index(
                                        organizationContext.active.id,
                                    )}
                                >
                                    <MapPin
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Manage locations
                                </Link>
                            </Button>
                        )}

                        {permissions.has('users.manage') && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={OrganizationMemberController.index(
                                        organizationContext.active.id,
                                    )}
                                >
                                    <Users
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Manage members
                                </Link>
                            </Button>
                        )}

                        {permissions.has('organization.manage') && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={OrganizationController.edit(
                                        organizationContext.active.id,
                                    )}
                                >
                                    <Settings
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Organization settings
                                </Link>
                            </Button>
                        )}

                        <Button asChild>
                            <Link href={OrganizationController.create()}>
                                <Plus className="size-4" aria-hidden="true" />
                                New organization
                            </Link>
                        </Button>
                    </div>
                </section>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                    {dashboardData.metrics.inventoryValue !== null && (
                        <DashboardMetricCard
                            title="Inventory value"
                            value={formatCurrency(
                                dashboardData.metrics.inventoryValue,
                                dashboardData.currency,
                            )}
                            description="Current materialized stock value"
                            icon={WalletCards}
                            tone="emerald"
                        />
                    )}

                    {dashboardData.metrics.lowStockItems !== null && (
                        <DashboardMetricCard
                            title="Low-stock items"
                            value={dashboardData.metrics.lowStockItems}
                            description="Items with zero or negative stock"
                            icon={AlertTriangle}
                            tone="amber"
                        />
                    )}

                    {dashboardData.metrics.openPurchaseOrders !== null && (
                        <DashboardMetricCard
                            title="Open purchase orders"
                            value={dashboardData.metrics.openPurchaseOrders}
                            description="Draft, approved, or partially received"
                            icon={ClipboardList}
                            tone="blue"
                        />
                    )}

                    {dashboardData.metrics.pendingReceiving !== null && (
                        <DashboardMetricCard
                            title="Pending receiving"
                            value={dashboardData.metrics.pendingReceiving}
                            description="Approved or partially received orders"
                            icon={Truck}
                            tone="teal"
                        />
                    )}

                    {dashboardData.metrics.openStockCounts !== null && (
                        <DashboardMetricCard
                            title="Open stock counts"
                            value={dashboardData.metrics.openStockCounts}
                            description="Draft or submitted counts"
                            icon={ClipboardCheck}
                            tone="violet"
                        />
                    )}
                </div>

                <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="grid min-w-0 gap-4">
                        <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                            <PanelHeader title="Your organizations" />

                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[720px] text-sm">
                                    <thead className="border-b border-sidebar-border/70 bg-muted/30 text-left text-xs text-muted-foreground dark:border-sidebar-border">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Organization
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Role
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Locations
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Members
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                        {organizationContext.memberships.map(
                                            (membership) => {
                                                const isActive =
                                                    membership.organization
                                                        .id ===
                                                    organizationContext.active
                                                        ?.id;

                                                const stats =
                                                    organizationStats.get(
                                                        membership.organization
                                                            .id,
                                                    );

                                                return (
                                                    <tr
                                                        key={
                                                            membership
                                                                .organization.id
                                                        }
                                                        className="hover:bg-muted/20"
                                                    >
                                                        <td className="px-4 py-3">
                                                            <div className="flex items-center gap-3">
                                                                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                                                    <Building2
                                                                        className="size-4 text-muted-foreground"
                                                                        aria-hidden="true"
                                                                    />
                                                                </div>

                                                                <p className="font-medium">
                                                                    {
                                                                        membership
                                                                            .organization
                                                                            .name
                                                                    }
                                                                </p>
                                                            </div>
                                                        </td>

                                                        <td className="px-4 py-3 text-muted-foreground capitalize">
                                                            {membership.role.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            {isActive ? (
                                                                <Badge variant="secondary">
                                                                    Active
                                                                </Badge>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    Available
                                                                </span>
                                                            )}
                                                        </td>

                                                        <td className="px-4 py-3 text-right tabular-nums">
                                                            {stats?.locationCount ??
                                                                0}
                                                        </td>

                                                        <td className="px-4 py-3 text-right tabular-nums">
                                                            {stats?.memberCount ??
                                                                0}
                                                        </td>

                                                        <td className="px-4 py-3 text-right">
                                                            <Form
                                                                {...OrganizationController.activate.form(
                                                                    membership
                                                                        .organization
                                                                        .id,
                                                                )}
                                                                onSuccess={() => {
                                                                    router.flushAll();
                                                                }}
                                                            >
                                                                {({
                                                                    processing,
                                                                }) => (
                                                                    <Button
                                                                        type="submit"
                                                                        size="sm"
                                                                        variant={
                                                                            isActive
                                                                                ? 'secondary'
                                                                                : 'outline'
                                                                        }
                                                                        disabled={
                                                                            processing ||
                                                                            isActive
                                                                        }
                                                                    >
                                                                        {isActive
                                                                            ? 'Current'
                                                                            : 'Switch'}
                                                                    </Button>
                                                                )}
                                                            </Form>
                                                        </td>
                                                    </tr>
                                                );
                                            },
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        {canViewReports && (
                            <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                                <PanelHeader
                                    title="Recent inventory activity"
                                    action={
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={StockMovementLedgerReportController.index()}
                                            >
                                                View all
                                            </Link>
                                        </Button>
                                    }
                                />

                                {dashboardData.recentActivity.length === 0 ? (
                                    <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                        No stock movements have been recorded
                                        yet.
                                    </p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[760px] text-sm">
                                            <thead className="border-b border-sidebar-border/70 bg-muted/30 text-left text-xs text-muted-foreground dark:border-sidebar-border">
                                                <tr>
                                                    <th className="px-4 py-3 font-medium">
                                                        Date & time
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Movement
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Item
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Location
                                                    </th>
                                                    <th className="px-4 py-3 font-medium">
                                                        Actor
                                                    </th>
                                                    <th className="px-4 py-3 text-right font-medium">
                                                        Quantity
                                                    </th>

                                                    {canViewCosts && (
                                                        <th className="px-4 py-3 text-right font-medium">
                                                            Value
                                                        </th>
                                                    )}
                                                </tr>
                                            </thead>

                                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                                {dashboardData.recentActivity.map(
                                                    (activity) => {
                                                        const isOutbound =
                                                            activity.quantity.startsWith(
                                                                '-',
                                                            );

                                                        const DirectionIcon =
                                                            isOutbound
                                                                ? ArrowDownRight
                                                                : ArrowUpRight;

                                                        return (
                                                            <tr
                                                                key={
                                                                    activity.id
                                                                }
                                                                className="hover:bg-muted/20"
                                                            >
                                                                <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                                    {formatDateTime(
                                                                        activity.occurredAt,
                                                                        dashboardData.timezone,
                                                                    )}
                                                                </td>

                                                                <td className="px-4 py-3">
                                                                    <div className="flex items-center gap-2">
                                                                        <DirectionIcon
                                                                            className="size-4 shrink-0 text-muted-foreground"
                                                                            aria-hidden="true"
                                                                        />

                                                                        <span>
                                                                            {movementLabels[
                                                                                activity
                                                                                    .type
                                                                            ] ??
                                                                                activity.type.replaceAll(
                                                                                    '_',
                                                                                    ' ',
                                                                                )}
                                                                        </span>
                                                                    </div>
                                                                </td>

                                                                <td className="px-4 py-3">
                                                                    <p className="font-medium">
                                                                        {
                                                                            activity.itemName
                                                                        }
                                                                    </p>

                                                                    <p className="text-xs text-muted-foreground">
                                                                        {
                                                                            activity.sku
                                                                        }
                                                                    </p>
                                                                </td>

                                                                <td className="px-4 py-3 text-muted-foreground">
                                                                    {
                                                                        activity.locationName
                                                                    }
                                                                </td>

                                                                <td className="px-4 py-3 text-muted-foreground">
                                                                    {activity.actorName ??
                                                                        'System'}
                                                                </td>

                                                                <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                                    {formatQuantity(
                                                                        activity.quantity,
                                                                    )}{' '}
                                                                    {
                                                                        activity.unitSymbol
                                                                    }
                                                                </td>

                                                                {canViewCosts && (
                                                                    <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                                        {activity.totalCost ===
                                                                        null
                                                                            ? '—'
                                                                            : formatCurrency(
                                                                                  activity.totalCost,
                                                                                  dashboardData.currency,
                                                                              )}
                                                                    </td>
                                                                )}
                                                            </tr>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </section>
                        )}
                    </div>

                    <aside className="grid gap-4">
                        {canViewReports && (
                            <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                                <PanelHeader
                                    title="Low stock alerts"
                                    action={
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={LowStockReportController.index()}
                                            >
                                                View all
                                            </Link>
                                        </Button>
                                    }
                                />

                                {dashboardData.lowStockAlerts.length === 0 ? (
                                    <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                                        No zero or negative stock balances.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-sidebar-border/70 px-4 dark:divide-sidebar-border">
                                        {dashboardData.lowStockAlerts.map(
                                            (alert) => (
                                                <div
                                                    key={alert.id}
                                                    className="flex gap-3 py-3"
                                                >
                                                    <AlertTriangle
                                                        className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                                                        aria-hidden="true"
                                                    />

                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-start justify-between gap-3">
                                                            <div className="min-w-0">
                                                                <p className="truncate text-sm font-medium">
                                                                    {
                                                                        alert.itemName
                                                                    }
                                                                </p>

                                                                <p className="truncate text-xs text-muted-foreground">
                                                                    {
                                                                        alert.locationName
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        alert.storageLocationName
                                                                    }
                                                                </p>
                                                            </div>

                                                            <div className="shrink-0 text-right">
                                                                <p className="text-sm font-semibold tabular-nums">
                                                                    {formatQuantity(
                                                                        alert.quantityOnHand,
                                                                    )}{' '}
                                                                    {
                                                                        alert.unitSymbol
                                                                    }
                                                                </p>

                                                                <Badge
                                                                    variant="outline"
                                                                    className="mt-1"
                                                                >
                                                                    Low
                                                                </Badge>
                                                            </div>
                                                        </div>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            SKU: {alert.sku}
                                                        </p>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </section>
                        )}

                        {hasPendingTasks && (
                            <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                                <PanelHeader title="Pending tasks" />

                                <div className="space-y-1 p-2">
                                    {dashboardData.pendingTasks
                                        .purchaseOrdersAwaitingApproval !==
                                        null && (
                                        <PendingTask
                                            href={PurchaseOrderController.index()}
                                            icon={ShoppingCart}
                                            label="Purchase orders awaiting approval"
                                            count={
                                                dashboardData.pendingTasks
                                                    .purchaseOrdersAwaitingApproval
                                            }
                                        />
                                    )}

                                    {dashboardData.pendingTasks
                                        .receiptsAwaitingFinalization !==
                                        null && (
                                        <PendingTask
                                            href={GoodsReceiptController.index()}
                                            icon={ReceiptText}
                                            label="Receipts awaiting finalization"
                                            count={
                                                dashboardData.pendingTasks
                                                    .receiptsAwaitingFinalization
                                            }
                                        />
                                    )}

                                    {dashboardData.pendingTasks
                                        .stockCountsAwaitingFinalization !==
                                        null && (
                                        <PendingTask
                                            href={StockCountController.index()}
                                            icon={ClipboardCheck}
                                            label="Stock counts awaiting finalization"
                                            count={
                                                dashboardData.pendingTasks
                                                    .stockCountsAwaitingFinalization
                                            }
                                        />
                                    )}
                                </div>
                            </section>
                        )}

                        {hasQuickActions && (
                            <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                                <PanelHeader title="Quick actions" />

                                <div className="grid grid-cols-2 gap-2 p-3">
                                    {permissions.has('purchasing.manage') && (
                                        <QuickAction
                                            href={PurchaseOrderController.create()}
                                            icon={ShoppingCart}
                                            label="New purchase order"
                                            description="Start a draft order"
                                        />
                                    )}

                                    {permissions.has('receiving.finalize') && (
                                        <QuickAction
                                            href={PurchaseOrderController.index()}
                                            icon={Truck}
                                            label="Record receiving"
                                            description="Choose an approved order"
                                        />
                                    )}

                                    {permissions.has('counts.create') && (
                                        <QuickAction
                                            href={StockCountController.create()}
                                            icon={ClipboardCheck}
                                            label="Start stock count"
                                            description="Create a physical count"
                                        />
                                    )}

                                    {permissions.has('waste.record') && (
                                        <QuickAction
                                            href={WasteController.index()}
                                            icon={Trash2}
                                            label="Log waste"
                                            description="Record approved waste"
                                        />
                                    )}
                                </div>
                            </section>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
