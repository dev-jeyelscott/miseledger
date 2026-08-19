import { Form, Head, Link } from '@inertiajs/react';
import {
    CircleMinus,
    ClipboardList,
    Clock,
    Coins,
    Filter,
    Package,
    PackageCheck,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-react';

import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type PurchaseOrderStatus =
    'draft' | 'approved' | 'partially_received' | 'received' | 'cancelled';

type PurchaseOrderRow = {
    id: number;
    number: string;
    status: PurchaseOrderStatus;
    supplierName: string;
    locationName: string;
    orderDate: string;
    expectedDeliveryDate: string | null;
    lineCount: number;
    total: string;
};

type PaginatedPurchaseOrders = {
    current_page: number;
    data: PurchaseOrderRow[];
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type PurchaseOrderSummary = {
    openCount: number;
    awaitingDeliveryCount: number;
    partiallyReceivedCount: number;
    thisMonthSpend: string | null;
};

type Option = {
    id: number;
    name: string;
};

type Props = {
    purchaseOrders: PaginatedPurchaseOrders;
    summary: PurchaseOrderSummary;
    supplierOptions: Option[];
    locationOptions: Option[];
    filters: {
        search: string | null;
        status: PurchaseOrderStatus | null;
        supplierId: number | null;
        locationId: number | null;
        from: string | null;
        to: string | null;
    };
    currency: string;
    canManage: boolean;
    canViewCosts: boolean;
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

const statusOptions: Array<{
    value: PurchaseOrderStatus;
    label: string;
}> = [
    {
        value: 'draft',
        label: 'Draft',
    },
    {
        value: 'approved',
        label: 'Approved',
    },
    {
        value: 'partially_received',
        label: 'Partially received',
    },
    {
        value: 'received',
        label: 'Received',
    },
    {
        value: 'cancelled',
        label: 'Cancelled',
    },
];

const dateFormatter = new Intl.DateTimeFormat('en-PH', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    timeZone: 'UTC',
});

/**
 * Format an authoritative decimal money string without JavaScript floats.
 */
function formatMoney(value: string): string {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const negative = rawInteger.startsWith('-');
    const integerDigits = negative ? rawInteger.slice(1) : rawInteger;

    const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    const decimal = rawDecimal.padEnd(2, '0').slice(0, 2);

    return `${negative ? '-' : ''}${groupedInteger}.${decimal}`;
}

/**
 * Format a persisted calendar date without browser timezone drift.
 */
function formatDate(value: string): string {
    return dateFormatter.format(new Date(`${value}T00:00:00Z`));
}

/**
 * Return the semantic badge treatment for one persisted PO status.
 */
function statusClassName(status: PurchaseOrderStatus): string {
    switch (status) {
        case 'draft':
            return 'border-border bg-muted/60 text-muted-foreground';

        case 'approved':
            return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300';

        case 'partially_received':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300';

        case 'received':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300';

        case 'cancelled':
            return 'border-destructive/30 bg-destructive/10 text-destructive dark:border-destructive/50 dark:bg-destructive/20';
    }
}

/**
 * Render an icon so status meaning never depends on color alone.
 */
function PurchaseOrderStatusIcon({ status }: { status: PurchaseOrderStatus }) {
    switch (status) {
        case 'draft':
            return <ClipboardList className="size-3" aria-hidden="true" />;

        case 'approved':
            return <Clock className="size-3" aria-hidden="true" />;

        case 'partially_received':
            return <Package className="size-3" aria-hidden="true" />;

        case 'received':
            return <PackageCheck className="size-3" aria-hidden="true" />;

        case 'cancelled':
            return <CircleMinus className="size-3" aria-hidden="true" />;
    }
}

/**
 * Convert the persisted status value into its operational label.
 */
function statusLabel(status: PurchaseOrderStatus): string {
    return (
        statusOptions.find((option) => option.value === status)?.label ?? status
    );
}

export default function PurchaseOrderIndex({
    purchaseOrders,
    summary,
    supplierOptions,
    locationOptions,
    filters,
    currency,
    canManage,
    canViewCosts,
}: Props) {
    const activeFilterCount = [
        filters.search,
        filters.status,
        filters.supplierId,
        filters.locationId,
        filters.from,
        filters.to,
    ].filter((value) => value !== null).length;

    const hasFilters = activeFilterCount > 0;

    return (
        <>
            <Head title="Purchase orders" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Purchase orders
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Order stock from configured suppliers and track PO
                            fulfillment.
                        </p>
                    </div>

                    {canManage && (
                        <Button asChild>
                            <Link href={PurchaseOrderController.create()}>
                                <Plus className="size-4" aria-hidden="true" />
                                Create purchase order
                            </Link>
                        </Button>
                    )}
                </div>

                <div
                    className={
                        canViewCosts
                            ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'
                            : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3'
                    }
                >
                    <DashboardMetricCard
                        title="Open POs"
                        value={summary.openCount.toLocaleString()}
                        description="Draft, approved, and partially received orders"
                        icon={ClipboardList}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Awaiting delivery"
                        value={summary.awaitingDeliveryCount.toLocaleString()}
                        description="Approved orders not yet receiving stock"
                        icon={Clock}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Partially received"
                        value={summary.partiallyReceivedCount.toLocaleString()}
                        description="Orders with remaining quantities to receive"
                        icon={Package}
                        tone="teal"
                    />

                    {canViewCosts && summary.thisMonthSpend !== null && (
                        <DashboardMetricCard
                            title="This month spend"
                            value={`${currency} ${formatMoney(
                                summary.thisMonthSpend,
                            )}`}
                            description="Approved, receiving, and received PO value ordered this month"
                            icon={Coins}
                            tone="violet"
                        />
                    )}
                </div>

                <Form action={PurchaseOrderController.index().url} method="get">
                    {({ errors, processing }) => (
                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                            <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-7">
                                <div className="grid gap-2 md:col-span-2 xl:col-span-2">
                                    <Label htmlFor="search">Search</Label>

                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />

                                        <Input
                                            id="search"
                                            name="search"
                                            type="search"
                                            defaultValue={filters.search ?? ''}
                                            placeholder="PO number or supplier"
                                            className="pl-9"
                                            maxLength={120}
                                            autoComplete="off"
                                            aria-invalid={
                                                errors.search ? true : undefined
                                            }
                                        />
                                    </div>

                                    <InputError message={errors.search} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>

                                    <select
                                        id="status"
                                        name="status"
                                        defaultValue={filters.status ?? ''}
                                        aria-invalid={
                                            errors.status ? true : undefined
                                        }
                                        className={selectClassName}
                                    >
                                        <option value="">All statuses</option>

                                        {statusOptions.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </select>

                                    <InputError message={errors.status} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="supplier_id">
                                        Supplier
                                    </Label>

                                    <select
                                        id="supplier_id"
                                        name="supplier_id"
                                        defaultValue={
                                            filters.supplierId?.toString() ?? ''
                                        }
                                        aria-invalid={
                                            errors.supplier_id
                                                ? true
                                                : undefined
                                        }
                                        className={selectClassName}
                                    >
                                        <option value="">All suppliers</option>

                                        {supplierOptions.map((supplier) => (
                                            <option
                                                key={supplier.id}
                                                value={supplier.id}
                                            >
                                                {supplier.name}
                                            </option>
                                        ))}
                                    </select>

                                    <InputError message={errors.supplier_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="location_id">
                                        Location
                                    </Label>

                                    <select
                                        id="location_id"
                                        name="location_id"
                                        defaultValue={
                                            filters.locationId?.toString() ?? ''
                                        }
                                        aria-invalid={
                                            errors.location_id
                                                ? true
                                                : undefined
                                        }
                                        className={selectClassName}
                                    >
                                        <option value="">All locations</option>

                                        {locationOptions.map((location) => (
                                            <option
                                                key={location.id}
                                                value={location.id}
                                            >
                                                {location.name}
                                            </option>
                                        ))}
                                    </select>

                                    <InputError message={errors.location_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="from">
                                        Order date from
                                    </Label>

                                    <Input
                                        id="from"
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from ?? ''}
                                        aria-invalid={
                                            errors.from ? true : undefined
                                        }
                                    />

                                    <InputError message={errors.from} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="to">Order date to</Label>

                                    <Input
                                        id="to"
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                        aria-invalid={
                                            errors.to ? true : undefined
                                        }
                                    />

                                    <InputError message={errors.to} />
                                </div>

                                <div className="flex items-end gap-2 md:col-span-2 xl:col-span-7 xl:justify-end">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-w-24"
                                    >
                                        <Filter
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing ? 'Applying…' : 'Apply'}
                                    </Button>

                                    <Button variant="outline" asChild>
                                        <Link
                                            href={PurchaseOrderController.index()}
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Clear
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}
                </Form>

                <section
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                    aria-labelledby="purchase-order-register-title"
                >
                    <div className="flex min-h-14 flex-col justify-center gap-1 border-b border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border">
                        <div>
                            <h2
                                id="purchase-order-register-title"
                                className="text-sm font-semibold"
                            >
                                Purchase order register
                            </h2>

                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {purchaseOrders.total.toLocaleString()}{' '}
                                {purchaseOrders.total === 1
                                    ? 'order'
                                    : 'orders'}
                                {hasFilters
                                    ? ` match ${activeFilterCount} active ${
                                          activeFilterCount === 1
                                              ? 'filter'
                                              : 'filters'
                                      }`
                                    : ' in this organization'}
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1080px] text-sm">
                            <caption className="sr-only">
                                Tenant-scoped purchase orders with supplier,
                                location, delivery, status, item count, and
                                total.
                            </caption>

                            <thead className="border-b bg-muted/40 text-left">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        PO number
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Supplier
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Location
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Order date
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Expected delivery
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Status
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium text-muted-foreground"
                                    >
                                        Items
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium text-muted-foreground"
                                    >
                                        Total
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium text-muted-foreground"
                                    >
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {purchaseOrders.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="px-6 py-14 text-center"
                                        >
                                            <div className="mx-auto flex size-10 items-center justify-center rounded-full bg-muted">
                                                <ClipboardList
                                                    className="size-5 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                            </div>

                                            <p className="mt-3 font-medium">
                                                {hasFilters
                                                    ? 'No purchase orders found'
                                                    : 'No purchase orders yet'}
                                            </p>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hasFilters
                                                    ? 'Adjust or clear the filters to view other orders.'
                                                    : canManage
                                                      ? 'Create a purchase order when stock needs to be ordered.'
                                                      : 'Purchase orders will appear here when they are created.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    purchaseOrders.data.map((purchaseOrder) => (
                                        <tr
                                            key={purchaseOrder.id}
                                            className="border-b transition-colors last:border-b-0 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PurchaseOrderController.edit(
                                                        purchaseOrder.id,
                                                    )}
                                                    className="font-medium text-blue-700 underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none dark:text-blue-300"
                                                >
                                                    {purchaseOrder.number}
                                                </Link>
                                            </td>

                                            <td className="px-4 py-3 font-medium">
                                                {purchaseOrder.supplierName}
                                            </td>

                                            <td className="px-4 py-3">
                                                {purchaseOrder.locationName}
                                            </td>

                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {formatDate(
                                                    purchaseOrder.orderDate,
                                                )}
                                            </td>

                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {purchaseOrder.expectedDeliveryDate ===
                                                null ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : (
                                                    formatDate(
                                                        purchaseOrder.expectedDeliveryDate,
                                                    )
                                                )}
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className={statusClassName(
                                                        purchaseOrder.status,
                                                    )}
                                                >
                                                    <PurchaseOrderStatusIcon
                                                        status={
                                                            purchaseOrder.status
                                                        }
                                                    />

                                                    {statusLabel(
                                                        purchaseOrder.status,
                                                    )}
                                                </Badge>
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {purchaseOrder.lineCount.toLocaleString()}
                                            </td>

                                            <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                {currency}{' '}
                                                {formatMoney(
                                                    purchaseOrder.total,
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={PurchaseOrderController.edit(
                                                            purchaseOrder.id,
                                                        )}
                                                    >
                                                        {canManage &&
                                                        purchaseOrder.status ===
                                                            'draft'
                                                            ? 'Edit'
                                                            : 'Open'}
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {purchaseOrders.total > 0 && (
                        <div className="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border">
                            <p className="text-sm text-muted-foreground">
                                Showing {purchaseOrders.from ?? 0} to{' '}
                                {purchaseOrders.to ?? 0} of{' '}
                                {purchaseOrders.total.toLocaleString()} purchase
                                orders
                            </p>

                            {purchaseOrders.last_page > 1 && (
                                <div className="flex items-center gap-2">
                                    {purchaseOrders.prev_page_url !== null ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    purchaseOrders.prev_page_url
                                                }
                                                preserveScroll
                                                preserveState
                                            >
                                                Previous
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled
                                        >
                                            Previous
                                        </Button>
                                    )}

                                    <span className="min-w-24 text-center text-sm text-muted-foreground">
                                        Page {purchaseOrders.current_page} of{' '}
                                        {purchaseOrders.last_page}
                                    </span>

                                    {purchaseOrders.next_page_url !== null ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    purchaseOrders.next_page_url
                                                }
                                                preserveScroll
                                                preserveState
                                            >
                                                Next
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled
                                        >
                                            Next
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

PurchaseOrderIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Purchase orders',
            href: PurchaseOrderController.index(),
        },
    ],
};
