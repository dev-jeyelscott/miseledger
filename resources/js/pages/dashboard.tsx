import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Building2,
    ClipboardCheck,
    ClipboardList,
    CreditCard,
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
import OrganizationBillingController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingController';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useGuardedDialog } from '@/hooks/use-guarded-dialog';
import { cn } from '@/lib/utils';
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

type OrganizationSettingsData = {
    id: number;
    name: string;
    slug: string;
    timezone: string;
    currency: string;
    active: boolean;
};

type DashboardData = {
    currency: string;
    timezone: string;
    organizationSettings: OrganizationSettingsData | null;
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

type DialogTriggerProps = {
    trigger: ReactNode;
};

type OrganizationSettingsDialogProps = DialogTriggerProps & {
    organization: OrganizationSettingsData;
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

/** Group integer digits without converting persisted fixed-precision values to floats. */
function groupInteger(value: string): string {
    const isNegative = value.startsWith('-');
    const digits = isNegative ? value.slice(1) : value;
    const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return isNegative ? `-${grouped}` : grouped;
}

/** Format a decimal money string while preserving authoritative persisted precision. */
function formatCurrency(value: string, currency: string): string {
    const [integerPart, fractionPart = ''] = value.split('.');
    const trimmedFraction = fractionPart.replace(/0+$/, '');
    const displayFraction = trimmedFraction.padEnd(2, '0');

    return `${currency} ${groupInteger(integerPart)}.${displayFraction}`;
}

/** Format a six-decimal inventory quantity without JavaScript floating-point conversion. */
function formatQuantity(value: string): string {
    const [integerPart, fractionPart = ''] = value.split('.');
    const trimmedFraction = fractionPart.replace(/0+$/, '');

    return trimmedFraction.length === 0
        ? groupInteger(integerPart)
        : `${groupInteger(integerPart)}.${trimmedFraction}`;
}

/** Render immutable ledger timestamps in the active organization's configured timezone. */
function formatDateTime(value: string, timeZone: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone,
    }).format(new Date(value));
}

/** Render a trial or subscription end date in the active organization's configured timezone. */
function formatDate(value: string, timeZone: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeZone,
    }).format(new Date(value));
}

/** Convert persisted enum-like strings into compact human-readable labels. */
function humanize(value: string): string {
    return value.replaceAll('_', ' ');
}

/** Keep dense dashboard panel headings and secondary actions visually consistent. */
function PanelHeader({ title, action }: PanelHeaderProps) {
    return (
        <div className="flex min-h-12 items-center justify-between gap-3 border-b border-border px-4">
            <h2 className="text-sm font-semibold">{title}</h2>
            {action}
        </div>
    );
}

/** Render one permission-gated operational task with a secondary numeric count. */
function PendingTask({ href, icon: Icon, label, count }: PendingTaskProps) {
    return (
        <Link
            href={href}
            className="flex min-h-10 items-center gap-3 rounded-lg px-2 py-2 text-sm transition-colors hover:bg-muted/60 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <Icon
                className="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />

            <span className="min-w-0 flex-1">{label}</span>

            <Badge variant="secondary" className="tabular-nums">
                {count}
            </Badge>
        </Link>
    );
}

/** Render a compact workflow shortcut using existing routes and permission boundaries. */
function QuickAction({
    href,
    icon: Icon,
    label,
    description,
}: QuickActionProps) {
    return (
        <Link
            href={href}
            className="group flex min-h-20 items-start gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground transition-colors group-hover:text-foreground">
                <Icon className="size-4" aria-hidden="true" />
            </div>

            <div className="min-w-0">
                <p className="text-sm font-medium">{label}</p>
                <p className="mt-1 text-xs leading-4 text-muted-foreground">
                    {description}
                </p>
            </div>
        </Link>
    );
}

/** Create a new organization without navigating away from the Dashboard context. */
function CreateOrganizationDialog({ trigger }: DialogTriggerProps) {
    const dialog = useGuardedDialog(
        'Discard the organization details you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Create organization</DialogTitle>
                    <DialogDescription>
                        Create the tenant boundary for your restaurant inventory
                        data.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...OrganizationController.store.form()}
                        errorBag="createOrganization"
                        className="space-y-6"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="dashboard-organization-name">
                                        Organization name
                                    </Label>
                                    <Input
                                        id="dashboard-organization-name"
                                        name="name"
                                        required
                                        maxLength={160}
                                        autoFocus
                                        autoComplete="organization"
                                        placeholder="Example Restaurant Group"
                                        aria-invalid={Boolean(errors.name)}
                                        aria-describedby={
                                            errors.name
                                                ? 'dashboard-organization-name-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="dashboard-organization-name-error"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Create organization
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() =>
                                            dialog.onOpenChange(false)
                                        }
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Edit the existing active-organization configuration inside its guarded dialog. */
function OrganizationSettingsDialog({
    organization,
    trigger,
}: OrganizationSettingsDialogProps) {
    const dialog = useGuardedDialog(
        'Discard the organization setting changes you entered?',
    );

    return (
        <Dialog open={dialog.open} onOpenChange={dialog.onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Organization settings</DialogTitle>
                    <DialogDescription>
                        Manage the configuration for {organization.name}.
                    </DialogDescription>
                </DialogHeader>

                <div onChange={dialog.markDirty}>
                    <Form
                        {...OrganizationController.update.form(organization.id)}
                        errorBag="organizationSettings"
                        className="space-y-5"
                        onSuccess={dialog.closeAfterSuccess}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="dashboard-settings-name">
                                        Name
                                    </Label>
                                    <Input
                                        id="dashboard-settings-name"
                                        name="name"
                                        defaultValue={organization.name}
                                        required
                                        maxLength={160}
                                        aria-invalid={Boolean(errors.name)}
                                        aria-describedby={
                                            errors.name
                                                ? 'dashboard-settings-name-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="dashboard-settings-name-error"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="dashboard-settings-slug">
                                        Slug
                                    </Label>
                                    <Input
                                        id="dashboard-settings-slug"
                                        name="slug"
                                        defaultValue={organization.slug}
                                        required
                                        maxLength={160}
                                        aria-invalid={Boolean(errors.slug)}
                                        aria-describedby={
                                            errors.slug
                                                ? 'dashboard-settings-slug-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="dashboard-settings-slug-error"
                                        message={errors.slug}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="dashboard-settings-timezone">
                                        Timezone
                                    </Label>
                                    <Input
                                        id="dashboard-settings-timezone"
                                        name="timezone"
                                        defaultValue={organization.timezone}
                                        required
                                        maxLength={64}
                                        placeholder="Asia/Manila"
                                        aria-invalid={Boolean(errors.timezone)}
                                        aria-describedby={
                                            errors.timezone
                                                ? 'dashboard-settings-timezone-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="dashboard-settings-timezone-error"
                                        message={errors.timezone}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="dashboard-settings-currency">
                                        Currency
                                    </Label>
                                    <Input
                                        id="dashboard-settings-currency"
                                        name="currency"
                                        defaultValue={organization.currency}
                                        required
                                        maxLength={3}
                                        placeholder="PHP"
                                        aria-invalid={Boolean(errors.currency)}
                                        aria-describedby={
                                            errors.currency
                                                ? 'dashboard-settings-currency-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="dashboard-settings-currency-error"
                                        message={errors.currency}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="dashboard-settings-active">
                                        Status
                                    </Label>
                                    <select
                                        id="dashboard-settings-active"
                                        name="active"
                                        defaultValue={
                                            organization.active ? '1' : '0'
                                        }
                                        aria-invalid={Boolean(errors.active)}
                                        aria-describedby={
                                            errors.active
                                                ? 'dashboard-settings-active-error'
                                                : undefined
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <InputError
                                        id="dashboard-settings-active-error"
                                        message={errors.active}
                                    />
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Save settings
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() =>
                                            dialog.onOpenChange(false)
                                        }
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Render the permission-aware operational command center for the active organization. */
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

                <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                    <header>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Set up an organization to start managing inventory
                            operations.
                        </p>
                    </header>

                    <section className="w-full max-w-xl rounded-xl border border-border bg-card p-6 shadow-sm">
                        <div className="flex size-11 items-center justify-center rounded-full bg-muted">
                            <Building2
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>

                        <h2 className="mt-4 text-xl font-semibold tracking-tight">
                            Create your organization
                        </h2>

                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                            An organization is required before
                            organization-scoped inventory data can be created.
                        </p>

                        <CreateOrganizationDialog
                            trigger={
                                <Button className="mt-6">
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Create organization
                                </Button>
                            }
                        />
                    </section>
                </div>
            </>
        );
    }

    const activeOrganization = organizationContext.active;

    const organizationStats = new Map(
        dashboardData.organizationStats.map((stat) => [
            stat.organizationId,
            stat,
        ]),
    );

    const activeOrganizationStats = organizationStats.get(
        activeOrganization.id,
    );

    const canViewReports = permissions.has('reports.view');
    const canViewCosts = permissions.has('costs.view');
    const canManagePurchasing = permissions.has('purchasing.manage');
    const canFinalizeReceiving = permissions.has('receiving.finalize');

    const pendingTaskCounts = [
        dashboardData.pendingTasks.purchaseOrdersAwaitingApproval,
        dashboardData.pendingTasks.receiptsAwaitingFinalization,
        dashboardData.pendingTasks.stockCountsAwaitingFinalization,
    ].filter((count): count is number => count !== null);

    const hasPendingTasks = pendingTaskCounts.length > 0;
    const hasPendingAttention = pendingTaskCounts.some((count) => count > 0);

    const hasQuickActions =
        canManagePurchasing ||
        canFinalizeReceiving ||
        permissions.has('counts.create') ||
        permissions.has('waste.record');

    const hasPendingWork = hasPendingTasks || hasQuickActions;

    const splitOperationalPanels = canViewReports && hasPendingWork;

    const subscription = organizationContext.subscription;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dashboard
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Operational overview for{' '}
                            <span className="font-medium text-foreground">
                                {activeOrganization.name}
                            </span>
                            .
                        </p>

                        <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                            <Badge
                                variant="outline"
                                className="border-success-border bg-success-subtle text-success-foreground"
                            >
                                Active
                            </Badge>

                            <span className="capitalize">
                                {activeMembership
                                    ? humanize(activeMembership.role)
                                    : 'Unknown role'}
                            </span>

                            <span aria-hidden="true">·</span>

                            <span>{dashboardData.timezone}</span>
                        </div>
                    </div>

                    {(canFinalizeReceiving || canManagePurchasing) && (
                        <div className="flex flex-wrap gap-2">
                            {canFinalizeReceiving && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={PurchaseOrderController.index()}
                                    >
                                        <Truck
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Receive stock
                                    </Link>
                                </Button>
                            )}

                            {canManagePurchasing && (
                                <Button asChild>
                                    <Link
                                        href={PurchaseOrderController.create()}
                                    >
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Create purchase order
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                </header>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
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

                {(canViewReports || hasPendingWork) && (
                    <div
                        className={cn(
                            'grid items-start gap-4',
                            splitOperationalPanels &&
                                'xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]',
                        )}
                    >
                        {canViewReports && (
                            <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                                <PanelHeader
                                    title="Low-stock alerts"
                                    action={
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={LowStockReportController.index()}
                                            >
                                                View low-stock report
                                            </Link>
                                        </Button>
                                    }
                                />

                                {dashboardData.lowStockAlerts.length === 0 ? (
                                    <div className="px-4 py-8 text-center">
                                        <p className="text-sm font-medium">
                                            Stock levels look clear
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            No zero or negative stock balances
                                            need attention.
                                        </p>
                                    </div>
                                ) : (
                                    <>
                                        <div className="hidden grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto_auto] gap-4 border-b border-border bg-muted/30 px-4 py-2.5 text-xs font-medium text-muted-foreground md:grid">
                                            <span>Item</span>
                                            <span>Location</span>
                                            <span className="text-right">
                                                On hand
                                            </span>
                                            <span>Status</span>
                                        </div>

                                        <div className="divide-y divide-border">
                                            {dashboardData.lowStockAlerts.map(
                                                (alert) => {
                                                    const isCritical =
                                                        alert.quantityOnHand.startsWith(
                                                            '-',
                                                        );

                                                    return (
                                                        <div
                                                            key={alert.id}
                                                            className="grid gap-3 px-4 py-3 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto_auto] md:items-center md:gap-4"
                                                        >
                                                            <div className="flex min-w-0 items-start gap-2.5">
                                                                <AlertTriangle
                                                                    className={cn(
                                                                        'mt-0.5 size-4 shrink-0',
                                                                        isCritical
                                                                            ? 'text-destructive'
                                                                            : 'text-warning-foreground',
                                                                    )}
                                                                    aria-hidden="true"
                                                                />

                                                                <div className="min-w-0">
                                                                    <p className="truncate text-sm font-medium">
                                                                        {
                                                                            alert.itemName
                                                                        }
                                                                    </p>
                                                                    <p className="truncate text-xs text-muted-foreground">
                                                                        {
                                                                            alert.sku
                                                                        }
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            <div className="min-w-0">
                                                                <p className="text-xs text-muted-foreground md:hidden">
                                                                    Location
                                                                </p>
                                                                <p className="truncate text-sm">
                                                                    {
                                                                        alert.locationName
                                                                    }
                                                                </p>
                                                                <p className="truncate text-xs text-muted-foreground">
                                                                    {
                                                                        alert.storageLocationName
                                                                    }
                                                                </p>
                                                            </div>

                                                            <div className="md:text-right">
                                                                <p className="text-xs text-muted-foreground md:hidden">
                                                                    On hand
                                                                </p>
                                                                <p className="text-sm font-semibold whitespace-nowrap tabular-nums">
                                                                    {formatQuantity(
                                                                        alert.quantityOnHand,
                                                                    )}{' '}
                                                                    {
                                                                        alert.unitSymbol
                                                                    }
                                                                </p>
                                                            </div>

                                                            <div>
                                                                <p className="mb-1 text-xs text-muted-foreground md:hidden">
                                                                    Status
                                                                </p>
                                                                <Badge
                                                                    variant="outline"
                                                                    className={
                                                                        isCritical
                                                                            ? 'border-destructive/30 bg-destructive/10 text-destructive'
                                                                            : 'border-warning-border bg-warning-subtle text-warning-foreground'
                                                                    }
                                                                >
                                                                    {isCritical
                                                                        ? 'Critical'
                                                                        : 'Low'}
                                                                </Badge>
                                                            </div>
                                                        </div>
                                                    );
                                                },
                                            )}
                                        </div>
                                    </>
                                )}
                            </section>
                        )}

                        {hasPendingWork && (
                            <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                                <PanelHeader title="Pending work" />

                                {hasPendingTasks && (
                                    <div className="p-3">
                                        <h3 className="px-2 text-xs font-semibold text-muted-foreground">
                                            Needs attention
                                        </h3>

                                        {hasPendingAttention ? (
                                            <div className="mt-1 space-y-1">
                                                {dashboardData.pendingTasks
                                                    .purchaseOrdersAwaitingApproval !==
                                                    null &&
                                                    dashboardData.pendingTasks
                                                        .purchaseOrdersAwaitingApproval >
                                                        0 && (
                                                        <PendingTask
                                                            href={PurchaseOrderController.index()}
                                                            icon={ShoppingCart}
                                                            label="Purchase orders awaiting approval"
                                                            count={
                                                                dashboardData
                                                                    .pendingTasks
                                                                    .purchaseOrdersAwaitingApproval
                                                            }
                                                        />
                                                    )}

                                                {dashboardData.pendingTasks
                                                    .receiptsAwaitingFinalization !==
                                                    null &&
                                                    dashboardData.pendingTasks
                                                        .receiptsAwaitingFinalization >
                                                        0 && (
                                                        <PendingTask
                                                            href={GoodsReceiptController.index()}
                                                            icon={ReceiptText}
                                                            label="Receipts awaiting finalization"
                                                            count={
                                                                dashboardData
                                                                    .pendingTasks
                                                                    .receiptsAwaitingFinalization
                                                            }
                                                        />
                                                    )}

                                                {dashboardData.pendingTasks
                                                    .stockCountsAwaitingFinalization !==
                                                    null &&
                                                    dashboardData.pendingTasks
                                                        .stockCountsAwaitingFinalization >
                                                        0 && (
                                                        <PendingTask
                                                            href={StockCountController.index()}
                                                            icon={
                                                                ClipboardCheck
                                                            }
                                                            label="Stock counts awaiting finalization"
                                                            count={
                                                                dashboardData
                                                                    .pendingTasks
                                                                    .stockCountsAwaitingFinalization
                                                            }
                                                        />
                                                    )}
                                            </div>
                                        ) : (
                                            <p className="px-2 pt-3 pb-1 text-sm text-muted-foreground">
                                                No pending approvals or
                                                finalizations.
                                            </p>
                                        )}
                                    </div>
                                )}

                                {hasQuickActions && (
                                    <div
                                        className={cn(
                                            'p-3',
                                            hasPendingTasks &&
                                                'border-t border-border',
                                        )}
                                    >
                                        <h3 className="px-1 text-xs font-semibold text-muted-foreground">
                                            Quick actions
                                        </h3>

                                        <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                            {canManagePurchasing && (
                                                <QuickAction
                                                    href={PurchaseOrderController.create()}
                                                    icon={ShoppingCart}
                                                    label="New purchase order"
                                                    description="Start a draft order"
                                                />
                                            )}

                                            {canFinalizeReceiving && (
                                                <QuickAction
                                                    href={GoodsReceiptController.index()}
                                                    icon={ReceiptText}
                                                    label="Finalize receipt"
                                                    description="Commit received stock"
                                                />
                                            )}

                                            {permissions.has(
                                                'counts.create',
                                            ) && (
                                                <QuickAction
                                                    href={StockCountController.create()}
                                                    icon={ClipboardCheck}
                                                    label="Start stock count"
                                                    description="Capture a physical count"
                                                />
                                            )}

                                            {permissions.has(
                                                'waste.record',
                                            ) && (
                                                <QuickAction
                                                    href={WasteController.index()}
                                                    icon={Trash2}
                                                    label="Record waste"
                                                    description="Record controlled stock out"
                                                />
                                            )}
                                        </div>
                                    </div>
                                )}
                            </section>
                        )}
                    </div>
                )}

                {canViewReports && (
                    <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                        <PanelHeader
                            title="Recent inventory activity"
                            action={
                                <Button variant="ghost" size="sm" asChild>
                                    <Link
                                        href={StockMovementLedgerReportController.index()}
                                    >
                                        View ledger
                                    </Link>
                                </Button>
                            }
                        />

                        {dashboardData.recentActivity.length === 0 ? (
                            <div className="px-4 py-8 text-center">
                                <p className="text-sm font-medium">
                                    No inventory activity yet
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Stock movements will appear here after
                                    inventory operations are recorded.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[840px] text-sm">
                                    <thead className="border-b border-border bg-muted/30 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Date & time
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Movement
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Item / SKU
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Location
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                Actor
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-medium"
                                            >
                                                Quantity
                                            </th>

                                            {canViewCosts && (
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3 text-right font-medium"
                                                >
                                                    Value
                                                </th>
                                            )}
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {dashboardData.recentActivity.map(
                                            (activity) => {
                                                const isOutbound =
                                                    activity.quantity.startsWith(
                                                        '-',
                                                    );

                                                const DirectionIcon = isOutbound
                                                    ? ArrowDownRight
                                                    : ArrowUpRight;

                                                return (
                                                    <tr
                                                        key={activity.id}
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
                                                                        humanize(
                                                                            activity.type,
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
                                                                {activity.sku}
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
                                                                    ? 'N/A'
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

                <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <PanelHeader title="Organization summary" />

                    <dl className="grid sm:grid-cols-2 lg:grid-cols-4">
                        <div className="p-4">
                            <dt className="text-xs font-medium text-muted-foreground">
                                Locations
                            </dt>
                            <dd className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                                {activeOrganizationStats?.locationCount ?? 0}
                            </dd>
                        </div>

                        <div className="p-4">
                            <dt className="text-xs font-medium text-muted-foreground">
                                Members
                            </dt>
                            <dd className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                                {activeOrganizationStats?.memberCount ?? 0}
                            </dd>
                        </div>

                        <div className="p-4">
                            <dt className="text-xs font-medium text-muted-foreground">
                                Your role
                            </dt>
                            <dd className="mt-2 text-sm font-medium capitalize">
                                {activeMembership
                                    ? humanize(activeMembership.role)
                                    : 'Unknown'}
                            </dd>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {dashboardData.currency} ·{' '}
                                {dashboardData.timezone}
                            </p>
                        </div>

                        <div className="p-4">
                            <dt className="text-xs font-medium text-muted-foreground">
                                Subscription
                            </dt>

                            {subscription === null ? (
                                <dd className="mt-2 text-sm text-muted-foreground">
                                    Not available
                                </dd>
                            ) : (
                                <dd className="mt-2">
                                    <p className="text-sm font-medium capitalize">
                                        {subscription.plan
                                            ? humanize(subscription.plan)
                                            : 'Plan unavailable'}
                                    </p>

                                    {subscription.status && (
                                        <p className="mt-1 text-xs text-muted-foreground capitalize">
                                            {humanize(subscription.status)}
                                        </p>
                                    )}

                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            'mt-2',
                                            subscription.accessMode ===
                                                'writable'
                                                ? 'border-success-border bg-success-subtle text-success-foreground'
                                                : 'border-warning-border bg-warning-subtle text-warning-foreground',
                                        )}
                                    >
                                        {subscription.accessMode === 'writable'
                                            ? 'Writable'
                                            : 'Read only'}
                                    </Badge>

                                    {subscription.onTrial &&
                                        subscription.trialEndsAt && (
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                Trial ends{' '}
                                                {formatDate(
                                                    subscription.trialEndsAt,
                                                    dashboardData.timezone,
                                                )}
                                            </p>
                                        )}
                                </dd>
                            )}
                        </div>
                    </dl>

                    <div className="flex flex-wrap gap-2 border-t border-border p-3">
                        {permissions.has('locations.manage') && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={OrganizationLocationController.index(
                                        activeOrganization.id,
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
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={OrganizationMemberController.index(
                                        activeOrganization.id,
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

                        {permissions.has('billing.manage') && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={OrganizationBillingController.show(
                                        activeOrganization.id,
                                    )}
                                >
                                    <CreditCard
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Set up billing
                                </Link>
                            </Button>
                        )}

                        {permissions.has('organization.manage') &&
                            dashboardData.organizationSettings !== null && (
                                <OrganizationSettingsDialog
                                    organization={
                                        dashboardData.organizationSettings
                                    }
                                    trigger={
                                        <Button variant="outline" size="sm">
                                            <Settings
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Organization settings
                                        </Button>
                                    }
                                />
                            )}

                        <CreateOrganizationDialog
                            trigger={
                                <Button variant="outline" size="sm">
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    New organization
                                </Button>
                            }
                        />
                    </div>
                </section>
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
