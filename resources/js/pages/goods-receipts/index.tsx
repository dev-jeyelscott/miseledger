import { Form, Head, Link } from '@inertiajs/react';
import {
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
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
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

/** Map persisted receipt lifecycle states to the shared semantic badge vocabulary. */
function statusVariant(
    status: GoodsReceiptStatus,
): 'neutral' | 'success' | 'warning' | 'info' | 'danger' {
    switch (status) {
        case 'draft':
            return 'warning';

        case 'finalized':
            return 'success';

        case 'cancelled':
            return 'danger';
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

/** Render one receiving register row as a mobile record card. */
function ReceiptCard({
    receipt,
    canFinalize,
    receivedAtFormatter,
}: {
    receipt: ReceiptRow;
    canFinalize: boolean;
    receivedAtFormatter: Intl.DateTimeFormat;
}) {
    return (
        <article className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Link
                        href={GoodsReceiptController.edit(receipt.id)}
                        className="font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        {receipt.number}
                    </Link>
                    <p className="mt-0.5 truncate text-xs text-muted-foreground">
                        {receipt.supplierName}
                    </p>
                </div>

                <StatusBadge
                    label={statusLabel(receipt.status)}
                    variant={statusVariant(receipt.status)}
                />
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt className="text-xs text-muted-foreground">
                        Purchase order
                    </dt>
                    <dd className="mt-0.5">
                        <Link
                            href={PurchaseOrderController.edit(
                                receipt.purchaseOrderId,
                            )}
                            className="font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            {receipt.purchaseOrderNumber}
                        </Link>
                    </dd>
                </div>

                <div>
                    <dt className="text-xs text-muted-foreground">Location</dt>
                    <dd className="mt-0.5 truncate">{receipt.locationName}</dd>
                </div>

                <div>
                    <dt className="text-xs text-muted-foreground">
                        Accepted lines
                    </dt>
                    <dd className="mt-0.5 tabular-nums">
                        {receipt.acceptedLineCount.toLocaleString()}
                    </dd>
                </div>

                <div>
                    <dt className="text-xs text-muted-foreground">
                        Received date
                    </dt>
                    <dd className="mt-0.5">
                        {receipt.receivedAt === null
                            ? receipt.status === 'draft'
                                ? 'Not finalized'
                                : 'Not recorded'
                            : receivedAtFormatter.format(
                                  new Date(receipt.receivedAt),
                              )}
                    </dd>
                </div>

                <div className="col-span-2">
                    <dt className="text-xs text-muted-foreground">
                        Received by
                    </dt>
                    <dd className="mt-0.5">
                        {receipt.receivedBy ?? 'Not recorded'}
                    </dd>
                </div>
            </dl>

            <div className="mt-4 border-t border-border pt-3">
                <Button variant="outline" size="sm" className="w-full" asChild>
                    <Link href={GoodsReceiptController.edit(receipt.id)}>
                        {actionLabel(receipt.status, canFinalize)}
                    </Link>
                </Button>
            </div>
        </article>
    );
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
    const activeFilterLabels = [
        filters.search === null ? null : `Search: ${filters.search}`,
        filters.status === null
            ? null
            : `Status: ${statusLabel(filters.status)}`,
        filters.locationId === null
            ? null
            : `Location: ${
                  locationOptions.find(
                      (option) => option.id === filters.locationId,
                  )?.name ?? filters.locationId
              }`,
        filters.supplierId === null
            ? null
            : `Supplier: ${
                  supplierOptions.find(
                      (option) => option.id === filters.supplierId,
                  )?.name ?? filters.supplierId
              }`,
        filters.from === null ? null : `From: ${filters.from}`,
        filters.to === null ? null : `To: ${filters.to}`,
    ].filter((label): label is string => label !== null);

    const hasFilters = activeFilterLabels.length > 0;

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
                <PageHeader
                    title="Receiving"
                    description="Track draft, finalized, and cancelled goods receipts across locations."
                    actions={
                        canFinalize ? (
                            <Button asChild>
                                <Link href={PurchaseOrderController.index()}>
                                    <Package
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Receive from purchase order
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

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
                        <FilterToolbar className="overflow-hidden p-0 shadow-sm">
                            <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-8">
                                <div className="md:col-span-2 xl:col-span-2">
                                    <Field
                                        id="search"
                                        label="Search"
                                        error={errors.search}
                                    >
                                        <div className="relative">
                                            <Search
                                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                aria-hidden="true"
                                            />

                                            <Input
                                                name="search"
                                                type="search"
                                                defaultValue={
                                                    filters.search ?? ''
                                                }
                                                placeholder="Receipt, PO, or supplier"
                                                className="pl-9"
                                                maxLength={120}
                                                autoComplete="off"
                                            />
                                        </div>
                                    </Field>
                                </div>

                                <Field
                                    id="status"
                                    label="Status"
                                    error={errors.status}
                                >
                                    <NativeSelect
                                        name="status"
                                        defaultValue={filters.status ?? ''}
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
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="location_id"
                                    label="Location"
                                    error={errors.location_id}
                                >
                                    <NativeSelect
                                        name="location_id"
                                        defaultValue={
                                            filters.locationId?.toString() ?? ''
                                        }
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
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="supplier_id"
                                    label="Supplier"
                                    error={errors.supplier_id}
                                >
                                    <NativeSelect
                                        name="supplier_id"
                                        defaultValue={
                                            filters.supplierId?.toString() ?? ''
                                        }
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
                                    </NativeSelect>
                                </Field>

                                <Field
                                    id="from"
                                    label="Received from"
                                    error={errors.from}
                                >
                                    <Input
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from ?? ''}
                                    />
                                </Field>

                                <Field
                                    id="to"
                                    label="Received to"
                                    error={errors.to}
                                >
                                    <Input
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                    />
                                </Field>

                                <Field
                                    id="sort"
                                    label="Sort"
                                    error={errors.sort}
                                >
                                    <NativeSelect
                                        name="sort"
                                        defaultValue={filters.sort}
                                    >
                                        {sortOptions.map((sort) => (
                                            <option
                                                key={sort.value}
                                                value={sort.value}
                                            >
                                                {sort.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>

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

                            <div
                                className="flex flex-wrap items-center gap-2 border-t border-border px-4 py-3 text-sm text-muted-foreground"
                                aria-live="polite"
                            >
                                <Filter className="size-4" aria-hidden="true" />

                                {activeFilterLabels.length === 0 ? (
                                    <Badge variant="outline">
                                        Active filters: None
                                    </Badge>
                                ) : (
                                    activeFilterLabels.map((label) => (
                                        <Badge key={label} variant="outline">
                                            {label}
                                        </Badge>
                                    ))
                                )}

                                {hasFilters && (
                                    <Link
                                        href={GoodsReceiptController.index()}
                                        className="font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        Clear all
                                    </Link>
                                )}
                            </div>
                        </FilterToolbar>
                    )}
                </Form>

                <section
                    className="grid gap-3 md:hidden"
                    aria-labelledby="receiving-register-cards-title"
                >
                    <h2 id="receiving-register-cards-title" className="sr-only">
                        Receiving register
                    </h2>

                    {receipts.data.length === 0 ? (
                        <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                            <EmptyState
                                icon={ClipboardList}
                                title={
                                    hasFilters
                                        ? 'No receipts found'
                                        : 'No goods receipts yet'
                                }
                                description={
                                    hasFilters
                                        ? 'Adjust or clear the filters to view other receiving history.'
                                        : canFinalize
                                          ? 'Start receiving from an approved or partially received purchase order.'
                                          : 'Goods receipts will appear here when receiving activity is recorded.'
                                }
                            />
                        </div>
                    ) : (
                        receipts.data.map((receipt) => (
                            <ReceiptCard
                                key={receipt.id}
                                receipt={receipt}
                                canFinalize={canFinalize}
                                receivedAtFormatter={receivedAtFormatter}
                            />
                        ))
                    )}
                </section>

                <section
                    className="hidden overflow-hidden rounded-xl border border-border bg-card shadow-sm md:block"
                    aria-labelledby="receiving-register-title"
                >
                    <div className="flex min-h-14 flex-col justify-center gap-1 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
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
                                    ? ` match ${activeFilterLabels.length} active ${
                                          activeFilterLabels.length === 1
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
                                        <td colSpan={9} className="px-6 py-14">
                                            <EmptyState
                                                icon={ClipboardList}
                                                title={
                                                    hasFilters
                                                        ? 'No receipts found'
                                                        : 'No goods receipts yet'
                                                }
                                                description={
                                                    hasFilters
                                                        ? 'Adjust or clear the filters to view other receiving history.'
                                                        : canFinalize
                                                          ? 'Start receiving from an approved or partially received purchase order.'
                                                          : 'Goods receipts will appear here when receiving activity is recorded.'
                                                }
                                            />
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
                                                    className="font-medium underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
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
                                                            : 'Not recorded'}
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
                                                <StatusBadge
                                                    label={statusLabel(
                                                        receipt.status,
                                                    )}
                                                    variant={statusVariant(
                                                        receipt.status,
                                                    )}
                                                />
                                            </td>

                                            <td className="max-w-56 px-4 py-3">
                                                {receipt.receivedBy === null ? (
                                                    <span className="text-muted-foreground">
                                                        Not recorded
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

                    <PaginationControls
                        currentPage={receipts.current_page}
                        lastPage={receipts.last_page}
                        from={receipts.from}
                        to={receipts.to}
                        total={receipts.total}
                        previousPageUrl={receipts.prev_page_url}
                        nextPageUrl={receipts.next_page_url}
                        itemLabel="receipts"
                        preserveScroll
                        preserveState
                    />
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
