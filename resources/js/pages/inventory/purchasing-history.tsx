import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    CircleMinus,
    ClipboardList,
    Clock,
    Coins,
    Download,
    Filter,
    Package,
    PackageCheck,
    RotateCcw,
    Search,
    TriangleAlert,
} from 'lucide-react';

import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import PurchasingHistoryReportController from '@/actions/App/Http/Controllers/Inventory/PurchasingHistoryReportController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { OrganizationContext } from '@/types';

type ReceiptState = 'received' | 'partial' | 'not_received' | 'over_received';

type PurchasingHistoryRow = {
    id: number;
    purchaseOrderId: number;
    purchaseOrderNumber: string;
    purchaseOrderStatus: string;
    orderDate: string;
    supplierId: number;
    supplierName: string;
    locationId: number;
    locationName: string;
    itemId: number;
    itemName: string;
    supplierSku: string;
    orderedQuantity: string;
    purchaseUnitSymbol: string;
    baseQuantity: string;
    baseUnitSymbol: string;
    receivedBaseQuantity: string;
    remainingBaseQuantity: string;
    overReceivedBaseQuantity: string;
    receiptState: ReceiptState;
    unitPrice: string | null;
    lineTotal: string | null;
};

type PurchasingHistorySummary = {
    totalPurchaseOrders: number;
    fullyReceivedCount: number;
    partialReceiptCount: number;
    totalSpend: string | null;
};

type Option = {
    id: number;
    name: string;
};

type Props = {
    rows: PurchasingHistoryRow[];
    summary: PurchasingHistorySummary;
    supplierOptions: Option[];
    locationOptions: Option[];
    filters: {
        supplierId: number | null;
        locationId: number | null;
        from: string | null;
        to: string | null;
        search: string | null;
        receiptState: ReceiptState | null;
    };
    currency: string;
    canViewCosts: boolean;
    canViewPurchaseOrders: boolean;
};

const receiptStateLabels: Record<ReceiptState, string> = {
    received: 'Received',
    partial: 'Partial',
    not_received: 'Pending',
    over_received: 'Over received',
};

const receiptStateOptions: Array<{
    value: '' | ReceiptState;
    label: string;
    dotClassName: string;
}> = [
    {
        value: '',
        label: 'All',
        dotClassName: 'bg-muted-foreground/40',
    },
    {
        value: 'received',
        label: 'Received',
        dotClassName: 'bg-emerald-500',
    },
    {
        value: 'partial',
        label: 'Partial',
        dotClassName: 'bg-amber-500',
    },
    {
        value: 'not_received',
        label: 'Pending',
        dotClassName: 'bg-muted-foreground/50',
    },
    {
        value: 'over_received',
        label: 'Over received',
        dotClassName: 'bg-destructive',
    },
];

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

/** Format persisted decimal strings without converting quantities or costs to floats. */
function formatDecimal(value: string): string {
    const [rawInteger, rawDecimal = ''] = value.trim().split('.');
    const negative = rawInteger.startsWith('-');
    const integerDigits = negative ? rawInteger.slice(1) : rawInteger;
    const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimal = rawDecimal.replace(/0+$/, '');

    return `${negative ? '-' : ''}${groupedInteger}${
        decimal === '' ? '' : `.${decimal}`
    }`;
}

/** Format one persisted currency amount without floating-point conversion. */
function formatCurrency(value: string, currency: string): string {
    return `${currency} ${formatDecimal(value)}`;
}

/** Convert persisted enum-like values to compact operational labels. */
function formatLabel(value: string): string {
    return value
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/** Return accessible semantic styling for one receipt state. */
function receiptStateClassName(state: ReceiptState): string {
    switch (state) {
        case 'received':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300';

        case 'partial':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300';

        case 'over_received':
            return 'border-destructive/30 bg-destructive/10 text-destructive dark:border-destructive/50 dark:bg-destructive/20';

        case 'not_received':
            return 'border-border bg-muted/50 text-muted-foreground';
    }
}

/** Render a non-color-only icon for each receipt-state badge. */
function ReceiptStateIcon({ state }: { state: ReceiptState }) {
    switch (state) {
        case 'received':
            return <PackageCheck className="size-3" aria-hidden="true" />;

        case 'partial':
            return <Clock className="size-3" aria-hidden="true" />;

        case 'over_received':
            return <TriangleAlert className="size-3" aria-hidden="true" />;

        case 'not_received':
            return <CircleMinus className="size-3" aria-hidden="true" />;
    }
}

/** Explain the ordered-versus-received result without relying only on status color. */
function receiptProgressLabel(row: PurchasingHistoryRow): string {
    switch (row.receiptState) {
        case 'received':
            return 'Complete';

        case 'partial':
            return `${formatDecimal(row.remainingBaseQuantity)} ${
                row.baseUnitSymbol
            } remaining`;

        case 'over_received':
            return `${formatDecimal(row.overReceivedBaseQuantity)} ${
                row.baseUnitSymbol
            } over`;

        case 'not_received':
            return 'Not received';
    }
}

/** Keep the existing CSV export synchronized with every active report filter. */
function buildExportUrl(filters: Props['filters']): string {
    const params = new URLSearchParams();

    if (filters.supplierId !== null) {
        params.set('supplier_id', filters.supplierId.toString());
    }

    if (filters.locationId !== null) {
        params.set('location_id', filters.locationId.toString());
    }

    if (filters.from !== null) {
        params.set('from', filters.from);
    }

    if (filters.to !== null) {
        params.set('to', filters.to);
    }

    if (filters.search !== null) {
        params.set('search', filters.search);
    }

    if (filters.receiptState !== null) {
        params.set('receipt_state', filters.receiptState);
    }

    const baseUrl = PurchasingHistoryReportController.export().url;
    const query = params.toString();

    return query === '' ? baseUrl : `${baseUrl}?${query}`;
}

/** Render purchasing and receiving history as a dense operational report. */
export default function PurchasingHistoryReport({
    rows,
    summary,
    supplierOptions,
    locationOptions,
    filters,
    currency,
    canViewCosts,
    canViewPurchaseOrders,
}: Props) {
    const activeFilterCount = [
        filters.supplierId,
        filters.locationId,
        filters.from,
        filters.to,
        filters.search,
        filters.receiptState,
    ].filter((value) => value !== null).length;

    const hasFilters = activeFilterCount > 0;
    const exportUrl = buildExportUrl(filters);

    const { organizationContext } = usePage<{
        organizationContext: OrganizationContext;
    }>().props;
    const canExportReports =
        organizationContext.entitlements?.grants['reports.export'] ?? false;

    return (
        <>
            <Head title="Purchasing history" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Purchasing history
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Purchase orders and receiving history with
                            ordered-versus-received quantities.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={InventoryItemController.index()}>
                            <Package className="size-4" aria-hidden="true" />
                            Inventory items
                        </Link>
                    </Button>
                </div>

                <div
                    className={
                        canViewCosts
                            ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'
                            : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3'
                    }
                >
                    <DashboardMetricCard
                        title="Total POs"
                        value={summary.totalPurchaseOrders.toLocaleString()}
                        description="Purchase orders represented by the current filters"
                        icon={ClipboardList}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Fully received"
                        value={summary.fullyReceivedCount.toLocaleString()}
                        description="Matching purchase orders currently marked received"
                        icon={PackageCheck}
                        tone="emerald"
                    />

                    <DashboardMetricCard
                        title="Partial receipts"
                        value={summary.partialReceiptCount.toLocaleString()}
                        description="Matching purchase orders still partially received"
                        icon={Clock}
                        tone="amber"
                    />

                    {canViewCosts && summary.totalSpend !== null && (
                        <DashboardMetricCard
                            title="Total spend"
                            value={formatCurrency(summary.totalSpend, currency)}
                            description="Historical PO line totals in the current filters"
                            icon={Coins}
                            tone="violet"
                        />
                    )}
                </div>

                <Form
                    action={PurchasingHistoryReportController.index().url}
                    method="get"
                >
                    {({ errors, processing }) => (
                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                            <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-5">
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
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="from">From</Label>

                                    <Input
                                        id="from"
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from ?? ''}
                                        aria-invalid={
                                            errors.from ? true : undefined
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="to">To</Label>

                                    <Input
                                        id="to"
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                        aria-invalid={
                                            errors.to ? true : undefined
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
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
                                            placeholder="Search PO / item / supplier"
                                            className="pl-9"
                                            maxLength={120}
                                            autoComplete="off"
                                            aria-invalid={
                                                errors.search ? true : undefined
                                            }
                                        />
                                    </div>
                                </div>

                                <fieldset className="min-w-0 md:col-span-2 xl:col-span-4">
                                    <legend className="mb-2 text-sm font-medium">
                                        Receipt status
                                    </legend>

                                    <div className="flex flex-wrap gap-2">
                                        {receiptStateOptions.map((option) => (
                                            <label
                                                key={
                                                    option.value === ''
                                                        ? 'all'
                                                        : option.value
                                                }
                                                className="cursor-pointer"
                                            >
                                                <input
                                                    type="radio"
                                                    name="receipt_state"
                                                    value={option.value}
                                                    defaultChecked={
                                                        filters.receiptState ===
                                                        null
                                                            ? option.value ===
                                                              ''
                                                            : filters.receiptState ===
                                                              option.value
                                                    }
                                                    className="peer sr-only"
                                                    aria-invalid={
                                                        errors.receipt_state
                                                            ? true
                                                            : undefined
                                                    }
                                                />

                                                <span className="inline-flex h-9 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-colors peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 hover:bg-accent hover:text-accent-foreground">
                                                    <span
                                                        className={`size-1.5 rounded-full ${option.dotClassName}`}
                                                        aria-hidden="true"
                                                    />

                                                    {option.label}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </fieldset>

                                <div className="flex items-end justify-end gap-2 md:col-span-2 xl:col-span-1">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="min-w-24 flex-1 xl:flex-none"
                                    >
                                        <Filter
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing ? 'Applying…' : 'Apply'}
                                    </Button>

                                    <Button
                                        variant="outline"
                                        className="flex-1 xl:flex-none"
                                        asChild
                                    >
                                        <Link
                                            href={PurchasingHistoryReportController.index()}
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Clear
                                        </Link>
                                    </Button>
                                </div>

                                {Object.keys(errors).length > 0 && (
                                    <div
                                        role="alert"
                                        className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive md:col-span-2 xl:col-span-5"
                                    >
                                        One or more report filters are invalid.
                                        Review the filter values or clear them
                                        and try again.
                                    </div>
                                )}
                            </div>

                            <div
                                className="flex flex-wrap items-center gap-2 border-t border-sidebar-border/70 px-4 py-3 text-sm text-muted-foreground dark:border-sidebar-border"
                                aria-live="polite"
                            >
                                <Filter className="size-4" aria-hidden="true" />

                                <span>
                                    Showing {rows.length.toLocaleString()}{' '}
                                    {rows.length === 1
                                        ? 'purchase line'
                                        : 'purchase lines'}
                                </span>

                                <Badge variant="outline">
                                    Active filters:{' '}
                                    {activeFilterCount === 0
                                        ? 'None'
                                        : activeFilterCount}
                                </Badge>
                            </div>
                        </div>
                    )}
                </Form>

                <section
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                    aria-labelledby="purchasing-history-table-title"
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 py-2 dark:border-sidebar-border">
                        <div>
                            <h2
                                id="purchasing-history-table-title"
                                className="text-sm font-semibold"
                            >
                                Purchase order lines
                            </h2>

                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Historical ordered quantities, receiving
                                progress, and purchase snapshots.
                            </p>
                        </div>

                        {canExportReports && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={exportUrl}>
                                    <Download
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Export CSV
                                </a>
                            </Button>
                        )}
                    </div>

                    <div className="overflow-x-auto">
                        <table
                            className={`w-full text-sm ${
                                canViewCosts
                                    ? 'min-w-[1320px]'
                                    : 'min-w-[1080px]'
                            }`}
                        >
                            <caption className="sr-only">
                                Purchasing history showing purchase order,
                                supplier, location, item, ordered quantity,
                                received quantity, receipt state, and permitted
                                historical cost information.
                            </caption>

                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        PO number
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Order date
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Supplier
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Location
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Item
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right"
                                    >
                                        Ordered
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right"
                                    >
                                        Received
                                    </th>

                                    <th scope="col" className="px-4 py-3">
                                        Receipt state
                                    </th>

                                    {canViewCosts && (
                                        <>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                Unit price
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                Line total
                                            </th>
                                        </>
                                    )}
                                </tr>
                            </thead>

                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canViewCosts ? 10 : 8}
                                            className="px-6 py-14 text-center"
                                        >
                                            <div className="mx-auto flex size-10 items-center justify-center rounded-full bg-muted">
                                                <Search
                                                    className="size-5 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                            </div>

                                            <p className="mt-3 font-medium">
                                                {hasFilters
                                                    ? 'No purchasing history matches these filters.'
                                                    : 'No purchasing history found.'}
                                            </p>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hasFilters
                                                    ? 'Adjust or clear the filters to view other purchase activity.'
                                                    : 'Purchase-order and receiving history will appear here when available.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-b border-sidebar-border/70 align-top transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3">
                                                {canViewPurchaseOrders ? (
                                                    <Link
                                                        href={PurchaseOrderController.edit(
                                                            row.purchaseOrderId,
                                                        )}
                                                        className="font-semibold text-blue-700 underline-offset-4 hover:underline dark:text-blue-300"
                                                    >
                                                        {
                                                            row.purchaseOrderNumber
                                                        }
                                                    </Link>
                                                ) : (
                                                    <div className="font-semibold">
                                                        {
                                                            row.purchaseOrderNumber
                                                        }
                                                    </div>
                                                )}

                                                <div className="mt-0.5 text-xs text-muted-foreground">
                                                    {formatLabel(
                                                        row.purchaseOrderStatus,
                                                    )}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {row.orderDate}
                                            </td>

                                            <td className="px-4 py-3">
                                                {row.supplierName}
                                            </td>

                                            <td className="px-4 py-3">
                                                {row.locationName}
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {row.itemName}
                                                </div>

                                                <div className="mt-0.5 text-xs text-muted-foreground">
                                                    {row.supplierSku}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                <div className="font-medium">
                                                    {formatDecimal(
                                                        row.orderedQuantity,
                                                    )}{' '}
                                                    <span className="font-normal text-muted-foreground">
                                                        {row.purchaseUnitSymbol}
                                                    </span>
                                                </div>

                                                <div className="mt-0.5 text-xs text-muted-foreground">
                                                    Base:{' '}
                                                    {formatDecimal(
                                                        row.baseQuantity,
                                                    )}{' '}
                                                    {row.baseUnitSymbol}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                <div className="font-medium">
                                                    {formatDecimal(
                                                        row.receivedBaseQuantity,
                                                    )}{' '}
                                                    <span className="font-normal text-muted-foreground">
                                                        {row.baseUnitSymbol}
                                                    </span>
                                                </div>

                                                <div className="mt-0.5 text-xs text-muted-foreground">
                                                    {receiptProgressLabel(row)}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className={receiptStateClassName(
                                                        row.receiptState,
                                                    )}
                                                >
                                                    <ReceiptStateIcon
                                                        state={row.receiptState}
                                                    />
                                                    {
                                                        receiptStateLabels[
                                                            row.receiptState
                                                        ]
                                                    }
                                                </Badge>
                                            </td>

                                            {canViewCosts && (
                                                <>
                                                    <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                        {row.unitPrice === null
                                                            ? '—'
                                                            : formatCurrency(
                                                                  row.unitPrice,
                                                                  currency,
                                                              )}
                                                    </td>

                                                    <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                        {row.lineTotal === null
                                                            ? '—'
                                                            : formatCurrency(
                                                                  row.lineTotal,
                                                                  currency,
                                                              )}
                                                    </td>
                                                </>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

PurchasingHistoryReport.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Inventory',
            href: InventoryItemController.index(),
        },
        {
            title: 'Purchasing history',
            href: PurchasingHistoryReportController.index(),
        },
    ],
};
