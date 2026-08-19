import { Form, Head, Link } from '@inertiajs/react';
import {
    CircleMinus,
    ClipboardList,
    Clock,
    Filter,
    Package,
    PackageCheck,
    RotateCcw,
    Search,
} from 'lucide-react';

import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type GoodsReceiptStatus = 'draft' | 'finalized' | 'cancelled';

type GoodsReceiptSort =
    'latest' | 'oldest' | 'receipt_asc' | 'receipt_desc' | 'status';

type ReceiptRow = {
    id: number;
    number: string;
    status: GoodsReceiptStatus;
    purchaseOrderId: number;
    purchaseOrderNumber: string;
    supplierName: string;
    locationName: string;
    acceptedLineCount: number;
    receivedAt: string | null;
    receivedBy: string | null;
};

type PaginatedReceipts = {
    current_page: number;
    data: ReceiptRow[];
    from: number | null;
    last_page: number;
    next_page_url: string | null;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

type ReceiptSummary = {
    totalCount: number;
    draftCount: number;
    finalizedCount: number;
    receivedThisWeekCount: number;
};

type Option = {
    id: number;
    name: string;
};

type Props = {
    receipts: PaginatedReceipts;
    summary: ReceiptSummary;
    supplierOptions: Option[];
    locationOptions: Option[];
    filters: {
        search: string | null;
        status: GoodsReceiptStatus | null;
        supplierId: number | null;
        locationId: number | null;
        from: string | null;
        to: string | null;
        sort: GoodsReceiptSort;
    };
    timezone: string;
    canFinalize: boolean;
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

const statusOptions: Array<{
    value: GoodsReceiptStatus;
    label: string;
}> = [
    {
        value: 'draft',
        label: 'Draft',
    },
    {
        value: 'finalized',
        label: 'Finalized',
    },
    {
        value: 'cancelled',
        label: 'Cancelled',
    },
];

const sortOptions: Array<{
    value: GoodsReceiptSort;
    label: string;
}> = [
    {
        value: 'latest',
        label: 'Latest first',
    },
    {
        value: 'oldest',
        label: 'Oldest first',
    },
    {
        value: 'receipt_asc',
        label: 'Receipt A-Z',
    },
    {
        value: 'receipt_desc',
        label: 'Receipt Z-A',
    },
    {
        value: 'status',
        label: 'Status',
    },
];

/**
 * Return the semantic badge treatment for one goods-receipt state.
 */
function statusClassName(status: GoodsReceiptStatus): string {
    switch (status) {
        case 'draft':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300';

        case 'finalized':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300';

        case 'cancelled':
            return 'border-destructive/30 bg-destructive/10 text-destructive dark:border-destructive/50 dark:bg-destructive/20';
    }
}

/**
 * Render an icon so receipt state never depends on color alone.
 */
function ReceiptStatusIcon({ status }: { status: GoodsReceiptStatus }) {
    switch (status) {
        case 'draft':
            return <Clock className="size-3" aria-hidden="true" />;

        case 'finalized':
            return <PackageCheck className="size-3" aria-hidden="true" />;

        case 'cancelled':
            return <CircleMinus className="size-3" aria-hidden="true" />;
    }
}

/**
 * Convert a persisted receipt status into its operational label.
 */
function statusLabel(status: GoodsReceiptStatus): string {
    return (
        statusOptions.find((option) => option.value === status)?.label ?? status
    );
}

/**
 * Return the safest contextual action label for one receipt.
 */
function actionLabel(status: GoodsReceiptStatus, canFinalize: boolean): string {
    if (status === 'draft' && canFinalize) {
        return 'Review & finalize';
    }

    if (status === 'draft') {
        return 'View draft';
    }

    return 'View';
}

export default function GoodsReceiptIndex({
    receipts,
    summary,
    supplierOptions,
    locationOptions,
    filters,
    timezone,
    canFinalize,
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

    const receivedAtFormatter = new Intl.DateTimeFormat('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timezone,
    });

    return (
        <>
            <Head title="Receiving" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Receiving
                        </h1>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Track draft, finalized, and cancelled goods receipts
                            across locations.
                        </p>
                    </div>

                    {canFinalize && (
                        <Button asChild>
                            <Link href={PurchaseOrderController.index()}>
                                <Package
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Receive from purchase order
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardMetricCard
                        title="Total receipts"
                        value={summary.totalCount.toLocaleString()}
                        description="All draft, finalized, and cancelled receipt records"
                        icon={ClipboardList}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Draft"
                        value={summary.draftCount.toLocaleString()}
                        description="Inventory-neutral until the receipt is finalized"
                        icon={Clock}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Finalized"
                        value={summary.finalizedCount.toLocaleString()}
                        description="Receipts posted through the stock-ledger workflow"
                        icon={PackageCheck}
                        tone="emerald"
                    />

                    <DashboardMetricCard
                        title="Received this week"
                        value={summary.receivedThisWeekCount.toLocaleString()}
                        description="Finalized during the current organization business week"
                        icon={Package}
                        tone="violet"
                    />
                </div>

                <Form action={GoodsReceiptController.index().url} method="get">
                    {({ errors, processing }) => (
                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                            <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-8">
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
                                            placeholder="Receipt, PO, or supplier"
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
                                    <Label htmlFor="from">Received from</Label>

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
                                    <Label htmlFor="to">Received to</Label>

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

                                <div className="grid gap-2">
                                    <Label htmlFor="sort">Sort</Label>

                                    <select
                                        id="sort"
                                        name="sort"
                                        defaultValue={filters.sort}
                                        aria-invalid={
                                            errors.sort ? true : undefined
                                        }
                                        className={selectClassName}
                                    >
                                        {sortOptions.map((sort) => (
                                            <option
                                                key={sort.value}
                                                value={sort.value}
                                            >
                                                {sort.label}
                                            </option>
                                        ))}
                                    </select>

                                    <InputError message={errors.sort} />
                                </div>

                                <div className="flex items-end gap-2 md:col-span-2 xl:col-span-8 xl:justify-end">
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
                                            href={GoodsReceiptController.index()}
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
                    aria-labelledby="receiving-register-title"
                >
                    <div className="flex min-h-14 flex-col justify-center gap-1 border-b border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border">
                        <div>
                            <h2
                                id="receiving-register-title"
                                className="text-sm font-semibold"
                            >
                                Receiving register
                            </h2>

                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {receipts.total.toLocaleString()}{' '}
                                {receipts.total === 1 ? 'receipt' : 'receipts'}
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
                        <table className="w-full min-w-[1180px] text-sm">
                            <caption className="sr-only">
                                Tenant-scoped goods receipts with purchase
                                order, supplier, location, accepted line count,
                                received timestamp, status, receiving actor, and
                                contextual action.
                            </caption>

                            <thead className="border-b bg-muted/40 text-left">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Receipt
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        PO
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
                                        className="px-4 py-3 text-right font-medium text-muted-foreground"
                                    >
                                        Accepted lines
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Received date
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Status
                                    </th>

                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium text-muted-foreground"
                                    >
                                        Received by
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
                                {receipts.data.length === 0 ? (
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
                                                    ? 'No receipts found'
                                                    : 'No goods receipts yet'}
                                            </p>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hasFilters
                                                    ? 'Adjust or clear the filters to view other receiving history.'
                                                    : canFinalize
                                                      ? 'Start receiving from an approved or partially received purchase order.'
                                                      : 'Goods receipts will appear here when receiving activity is recorded.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    receipts.data.map((receipt) => (
                                        <tr
                                            key={receipt.id}
                                            className="border-b transition-colors last:border-b-0 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={GoodsReceiptController.edit(
                                                        receipt.id,
                                                    )}
                                                    className="font-medium text-blue-700 underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none dark:text-blue-300"
                                                >
                                                    {receipt.number}
                                                </Link>
                                            </td>

                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PurchaseOrderController.edit(
                                                        receipt.purchaseOrderId,
                                                    )}
                                                    className="font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                >
                                                    {
                                                        receipt.purchaseOrderNumber
                                                    }
                                                </Link>
                                            </td>

                                            <td className="max-w-64 px-4 py-3 font-medium">
                                                <span
                                                    className="block truncate"
                                                    title={receipt.supplierName}
                                                >
                                                    {receipt.supplierName}
                                                </span>
                                            </td>

                                            <td className="max-w-56 px-4 py-3">
                                                <span
                                                    className="block truncate"
                                                    title={receipt.locationName}
                                                >
                                                    {receipt.locationName}
                                                </span>
                                            </td>

                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {receipt.acceptedLineCount.toLocaleString()}
                                            </td>

                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {receipt.receivedAt === null ? (
                                                    <span className="text-muted-foreground">
                                                        {receipt.status ===
                                                        'draft'
                                                            ? 'Not finalized'
                                                            : '—'}
                                                    </span>
                                                ) : (
                                                    receivedAtFormatter.format(
                                                        new Date(
                                                            receipt.receivedAt,
                                                        ),
                                                    )
                                                )}
                                            </td>

                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant="outline"
                                                    className={statusClassName(
                                                        receipt.status,
                                                    )}
                                                >
                                                    <ReceiptStatusIcon
                                                        status={receipt.status}
                                                    />

                                                    {statusLabel(
                                                        receipt.status,
                                                    )}
                                                </Badge>
                                            </td>

                                            <td className="max-w-56 px-4 py-3">
                                                {receipt.receivedBy === null ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : (
                                                    <span
                                                        className="block truncate"
                                                        title={
                                                            receipt.receivedBy
                                                        }
                                                    >
                                                        {receipt.receivedBy}
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={GoodsReceiptController.edit(
                                                            receipt.id,
                                                        )}
                                                    >
                                                        {actionLabel(
                                                            receipt.status,
                                                            canFinalize,
                                                        )}
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {receipts.total > 0 && (
                        <div className="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-sidebar-border">
                            <p className="text-sm text-muted-foreground">
                                Showing {receipts.from ?? 0} to{' '}
                                {receipts.to ?? 0} of{' '}
                                {receipts.total.toLocaleString()} receipts
                            </p>

                            {receipts.last_page > 1 && (
                                <div className="flex items-center gap-2">
                                    {receipts.prev_page_url !== null ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={receipts.prev_page_url}
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
                                        Page {receipts.current_page} of{' '}
                                        {receipts.last_page}
                                    </span>

                                    {receipts.next_page_url !== null ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={receipts.next_page_url}
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

GoodsReceiptIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Receiving',
            href: GoodsReceiptController.index(),
        },
    ],
};
