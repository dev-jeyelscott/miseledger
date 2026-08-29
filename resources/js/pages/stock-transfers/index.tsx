import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    ChevronsUpDown,
    FilePenLine,
    Filter,
    Info,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    TriangleAlert,
    Truck,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { EmptyState } from '@/components/empty-state';
import { FilterToolbar } from '@/components/filter-toolbar';
import { PageHeader } from '@/components/page-header';
import { PaginationControls } from '@/components/pagination-controls';
import { StatusBadge } from '@/components/status-badge';
import type { StatusBadgeProps } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';

type StockTransferStatus = 'draft' | 'shipped' | 'received' | 'cancelled';
type StockTransferView =
    'all' | 'draft' | 'shipped' | 'received' | 'cancelled' | 'variance';
type StockTransferSort =
    | 'latest'
    | 'number'
    | 'status'
    | 'requested_at'
    | 'shipped_at'
    | 'received_at';
type SortDirection = 'asc' | 'desc';
type StockTransferRow = {
    id: number;
    number: string;
    status: StockTransferStatus;
    fromLocationName: string;
    fromStorageLocationName: string;
    toLocationName: string;
    toStorageLocationName: string;
    requestedAt: string | null;
    shippedAt: string | null;
    receivedAt: string | null;
    itemCount: number;
    varianceItemCount: number | null;
    requestedByName: string | null;
};
type StockTransferSummary = {
    draftCount: number;
    shippedCount: number;
    receivedCount: number;
    varianceCount: number;
};
type Pagination = {
    currentPage: number;
    from: number | null;
    lastPage: number;
    nextPageUrl: string | null;
    perPage: number;
    previousPageUrl: string | null;
    to: number | null;
    total: number;
};
type LocationOption = { id: number; name: string };
type Filters = {
    search: string | null;
    view: StockTransferView;
    fromLocationId: number | null;
    toLocationId: number | null;
    from: string | null;
    to: string | null;
    sort: StockTransferSort;
    direction: SortDirection;
    perPage: number;
};
type Props = {
    rows: StockTransferRow[];
    pagination: Pagination;
    summary: StockTransferSummary;
    locationOptions: LocationOption[];
    filters: Filters;
    timezone: string;
    canCreate: boolean;
    canViewReport: boolean;
};

const quickViews: Array<{ label: string; value: StockTransferView }> = [
    { label: 'All', value: 'all' },
    { label: 'Draft', value: 'draft' },
    { label: 'Awaiting receipt', value: 'shipped' },
    { label: 'Received', value: 'received' },
    { label: 'Variance', value: 'variance' },
];
const viewLabels: Record<StockTransferView, string> = {
    all: 'All statuses',
    draft: 'Draft',
    shipped: 'Awaiting receipt',
    received: 'Received',
    cancelled: 'Cancelled',
    variance: 'Has variance',
};

function formatOrganizationDate(value: string, timezone: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'numeric',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
    }).format(new Date(value));
}
function transferStatusVariant(
    status: StockTransferStatus,
): StatusBadgeProps['variant'] {
    if (status === 'received') {
        return 'success';
    }

    if (status === 'shipped') {
        return 'info';
    }

    if (status === 'cancelled') {
        return 'danger';
    }

    return 'neutral';
}
function transferStatusLabel(status: StockTransferStatus): string {
    return status === 'shipped'
        ? 'Awaiting receipt'
        : status.charAt(0).toUpperCase() + status.slice(1);
}
function SortIndicator({
    active,
    direction,
}: {
    active: boolean;
    direction: SortDirection;
}) {
    if (!active) {
        return <ChevronsUpDown className="size-3.5" aria-hidden="true" />;
    }

    return direction === 'asc' ? (
        <ChevronUp className="size-3.5" aria-hidden="true" />
    ) : (
        <ChevronDown className="size-3.5" aria-hidden="true" />
    );
}
function VarianceState({ count }: { count: number | null }) {
    if (count === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return count === 0 ? (
        <StatusBadge label="No variance" variant="success" />
    ) : (
        <StatusBadge
            label={`${count.toLocaleString()} ${count === 1 ? 'item' : 'items'} with variance`}
            variant="danger"
        />
    );
}
function TransferDirection({ row }: { row: StockTransferRow }) {
    return (
        <div className="flex min-w-0 items-center gap-2">
            <div className="min-w-0">
                <p className="truncate font-medium">{row.fromLocationName}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {row.fromStorageLocationName}
                </p>
            </div>
            <ArrowRight
                className="size-4 shrink-0 text-muted-foreground"
                aria-label="to"
            />
            <div className="min-w-0">
                <p className="truncate font-medium">{row.toLocationName}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {row.toStorageLocationName}
                </p>
            </div>
        </div>
    );
}

/** Render the server-authoritative Stock Transfers operational index. */
export default function StockTransferIndex({
    rows,
    pagination,
    summary,
    locationOptions,
    filters,
    timezone,
    canCreate,
    canViewReport,
}: Props) {
    const [isNavigating, setIsNavigating] = useState(false);
    const source = locationOptions.find(
        (location) => location.id === filters.fromLocationId,
    );
    const destination = locationOptions.find(
        (location) => location.id === filters.toLocationId,
    );
    const activeFilters = [
        filters.search
            ? { label: `Search: ${filters.search}`, key: 'search' }
            : null,
        filters.view !== 'all'
            ? { label: `Status: ${viewLabels[filters.view]}`, key: 'view' }
            : null,
        source
            ? { label: `Source: ${source.name}`, key: 'from_location_id' }
            : null,
        destination
            ? {
                  label: `Destination: ${destination.name}`,
                  key: 'to_location_id',
              }
            : null,
        filters.from ? { label: `From: ${filters.from}`, key: 'from' } : null,
        filters.to ? { label: `To: ${filters.to}`, key: 'to' } : null,
    ].filter(
        (filter): filter is { label: string; key: string } => filter !== null,
    );
    const hrefFor = (
        changes: Record<string, string | number | null>,
    ): string => {
        const params = new URLSearchParams();

        if (filters.search !== null) {
            params.set('search', filters.search);
        }

        params.set('view', filters.view);

        if (filters.fromLocationId !== null) {
            params.set('from_location_id', filters.fromLocationId.toString());
        }

        if (filters.toLocationId !== null) {
            params.set('to_location_id', filters.toLocationId.toString());
        }

        if (filters.from !== null) {
            params.set('from', filters.from);
        }

        if (filters.to !== null) {
            params.set('to', filters.to);
        }

        params.set('sort', filters.sort);
        params.set('direction', filters.direction);
        params.set('per_page', filters.perPage.toString());
        Object.entries(changes).forEach(([key, value]) => {
            if (value === null || value === '') {
                params.delete(key);

                return;
            }

            params.set(key, value.toString());
        });

        return `${StockTransferController.index().url}?${params.toString()}`;
    };
    const sortHref = (sort: StockTransferSort): string =>
        hrefFor({
            sort,
            direction:
                filters.sort === sort && filters.direction === 'asc'
                    ? 'desc'
                    : 'asc',
            page: null,
        });
    useEffect(() => {
        const removeStartListener = router.on('start', () =>
            setIsNavigating(true),
        );
        const removeFinishListener = router.on('finish', () =>
            setIsNavigating(false),
        );

        return () => {
            removeStartListener();
            removeFinishListener();
        };
    }, []);
    const sortHeaders: Array<[StockTransferSort, string]> = [
        ['number', 'Number'],
        ['status', 'Status'],
        ['requested_at', 'Requested'],
        ['shipped_at', 'Shipped'],
        ['received_at', 'Received'],
    ];

    return (
        <>
            <Head title="Stock transfers" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Stock transfers"
                    description="Move inventory safely between storage locations."
                    actions={
                        <>
                            {canViewReport && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={StockTransferController.variance()}
                                    >
                                        <TriangleAlert
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Variance report
                                    </Link>
                                </Button>
                            )}
                            {canCreate && (
                                <Button asChild>
                                    <Link
                                        href={StockTransferController.create()}
                                    >
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        New transfer
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardMetricCard
                        title="Draft transfers"
                        value={summary.draftCount.toLocaleString()}
                        description="Editable transfer drafts"
                        icon={FilePenLine}
                        tone="violet"
                    />
                    <DashboardMetricCard
                        title="Awaiting receipt"
                        value={summary.shippedCount.toLocaleString()}
                        description="Shipped stock not yet received"
                        icon={Truck}
                        tone="blue"
                    />
                    <DashboardMetricCard
                        title="Received transfers"
                        value={summary.receivedCount.toLocaleString()}
                        description="Completed transfer receipts"
                        icon={CheckCircle2}
                        tone="emerald"
                    />
                    <DashboardMetricCard
                        title="Transfers with variance"
                        value={summary.varianceCount.toLocaleString()}
                        description="Received transfers with quantity variance"
                        icon={TriangleAlert}
                        tone="amber"
                    />
                </div>
                <Form action={StockTransferController.index().url} method="get">
                    {({ errors, processing }) => (
                        <FilterToolbar>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.35fr_0.85fr_1fr_1fr_0.8fr_0.8fr_auto]">
                                <input
                                    type="hidden"
                                    name="sort"
                                    value={filters.sort}
                                />
                                <input
                                    type="hidden"
                                    name="direction"
                                    value={filters.direction}
                                />
                                <input
                                    type="hidden"
                                    name="per_page"
                                    value={filters.perPage}
                                />
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
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search ?? ''}
                                            placeholder="Number, location, storage, or requester"
                                            className="pl-9"
                                            autoComplete="off"
                                        />
                                    </div>
                                </Field>
                                <Field
                                    id="view"
                                    label="Status"
                                    error={errors.view}
                                >
                                    <NativeSelect
                                        name="view"
                                        defaultValue={filters.view}
                                    >
                                        <option value="all">
                                            All statuses
                                        </option>
                                        <option value="draft">Draft</option>
                                        <option value="shipped">
                                            Awaiting receipt
                                        </option>
                                        <option value="received">
                                            Received
                                        </option>
                                        <option value="cancelled">
                                            Cancelled
                                        </option>
                                        <option value="variance">
                                            Has variance
                                        </option>
                                    </NativeSelect>
                                </Field>
                                <Field
                                    id="from_location_id"
                                    label="Source location"
                                    error={errors.from_location_id}
                                >
                                    <NativeSelect
                                        name="from_location_id"
                                        defaultValue={
                                            filters.fromLocationId?.toString() ??
                                            ''
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
                                    id="to_location_id"
                                    label="Destination location"
                                    error={errors.to_location_id}
                                >
                                    <NativeSelect
                                        name="to_location_id"
                                        defaultValue={
                                            filters.toLocationId?.toString() ??
                                            ''
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
                                    id="from"
                                    label="Requested from"
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
                                    label="Requested to"
                                    error={errors.to}
                                >
                                    <Input
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to ?? ''}
                                    />
                                </Field>
                                <div className="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="flex-1 xl:flex-none"
                                    >
                                        <Filter
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing
                                            ? 'Applying…'
                                            : 'Apply filters'}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        className="flex-1 xl:flex-none"
                                        asChild
                                    >
                                        <Link
                                            href={StockTransferController.index()}
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Reset
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                            {activeFilters.length > 0 && (
                                <div
                                    className="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4"
                                    aria-label="Active filters"
                                >
                                    <span className="text-xs font-medium text-muted-foreground">
                                        Active filters
                                    </span>
                                    {activeFilters.map((filter) => (
                                        <Button
                                            key={filter.key}
                                            variant="outline"
                                            size="sm"
                                            className="h-7 gap-1 px-2 text-xs"
                                            asChild
                                        >
                                            <Link
                                                href={hrefFor({
                                                    [filter.key]: null,
                                                    page: null,
                                                })}
                                                preserveScroll
                                                preserveState
                                            >
                                                <span>{filter.label}</span>
                                                <X
                                                    className="size-3"
                                                    aria-hidden="true"
                                                />
                                                <span className="sr-only">
                                                    Remove {filter.label} filter
                                                </span>
                                            </Link>
                                        </Button>
                                    ))}
                                </div>
                            )}
                            {Object.keys(errors).length > 0 && (
                                <div
                                    role="alert"
                                    className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                                >
                                    One or more stock-transfer filters are
                                    invalid. Review the described fields or
                                    reset the filters and try again.
                                </div>
                            )}
                        </FilterToolbar>
                    )}
                </Form>
                <nav
                    aria-label="Stock transfer views"
                    className="flex gap-1 overflow-x-auto border-b border-border px-1"
                >
                    {quickViews.map((view) => (
                        <Link
                            key={view.value}
                            href={hrefFor({ view: view.value, page: null })}
                            preserveScroll
                            preserveState
                            aria-current={
                                filters.view === view.value ? 'page' : undefined
                            }
                            className={cn(
                                'border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:transition-none',
                                filters.view === view.value
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {view.label}
                        </Link>
                    ))}
                </nav>
                <section
                    aria-labelledby="stock-transfers-table-title"
                    aria-busy={isNavigating}
                    className={cn(
                        'overflow-hidden rounded-xl border border-border bg-card text-card-foreground transition-opacity motion-reduce:transition-none',
                        isNavigating && 'opacity-60',
                    )}
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div>
                            <h2
                                id="stock-transfers-table-title"
                                className="text-sm font-semibold"
                            >
                                Stock transfer history
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Transfer lifecycle and receipt variance evidence
                                for the active organization.
                            </p>
                        </div>
                        <StatusBadge
                            label={`${pagination.total.toLocaleString()} ${pagination.total === 1 ? 'transfer' : 'transfers'}`}
                            variant="neutral"
                        />
                    </div>
                    <p className="sr-only" aria-live="polite">
                        {isNavigating ? 'Updating stock transfer results…' : ''}
                    </p>
                    {rows.length === 0 ? (
                        <EmptyState
                            className="px-6 py-14"
                            icon={Search}
                            title={
                                activeFilters.length > 0
                                    ? 'No stock transfers match these filters'
                                    : 'No stock transfers yet'
                            }
                            description={
                                activeFilters.length > 0
                                    ? 'Adjust or remove filters to see other transfer history.'
                                    : 'Create a transfer when inventory needs to move between storage locations.'
                            }
                            action={
                                activeFilters.length > 0 ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={StockTransferController.index()}
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Reset filters
                                        </Link>
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : (
                        <>
                            <div
                                className="divide-y divide-border md:hidden"
                                data-testid="mobile-stock-transfers"
                            >
                                {rows.map((row) => {
                                    const editable =
                                        canCreate && row.status === 'draft';

                                    return (
                                        <article
                                            key={row.id}
                                            className="space-y-4 p-4"
                                            aria-labelledby={`stock-transfer-${row.id}`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <Link
                                                        id={`stock-transfer-${row.id}`}
                                                        href={StockTransferController.edit(
                                                            row.id,
                                                        )}
                                                        className="font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    >
                                                        {row.number}
                                                    </Link>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Requested{' '}
                                                        {row.requestedAt
                                                            ? formatOrganizationDate(
                                                                  row.requestedAt,
                                                                  timezone,
                                                              )
                                                            : '—'}
                                                    </p>
                                                </div>
                                                <StatusBadge
                                                    label={transferStatusLabel(
                                                        row.status,
                                                    )}
                                                    variant={transferStatusVariant(
                                                        row.status,
                                                    )}
                                                />
                                            </div>
                                            <TransferDirection row={row} />
                                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Items
                                                    </dt>
                                                    <dd className="mt-1 tabular-nums">
                                                        {row.itemCount.toLocaleString()}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Variance
                                                    </dt>
                                                    <dd className="mt-1">
                                                        <VarianceState
                                                            count={
                                                                row.varianceItemCount
                                                            }
                                                        />
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Shipped
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {row.shippedAt
                                                            ? formatOrganizationDate(
                                                                  row.shippedAt,
                                                                  timezone,
                                                              )
                                                            : '—'}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-muted-foreground">
                                                        Received
                                                    </dt>
                                                    <dd className="mt-1">
                                                        {row.receivedAt
                                                            ? formatOrganizationDate(
                                                                  row.receivedAt,
                                                                  timezone,
                                                              )
                                                            : '—'}
                                                    </dd>
                                                </div>
                                            </dl>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                                asChild
                                            >
                                                <Link
                                                    href={StockTransferController.edit(
                                                        row.id,
                                                    )}
                                                >
                                                    {editable && (
                                                        <Pencil
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                    {editable
                                                        ? 'Edit transfer'
                                                        : 'View transfer'}
                                                </Link>
                                            </Button>
                                        </article>
                                    );
                                })}
                            </div>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[1260px] text-sm">
                                    <caption className="sr-only">
                                        Stock transfers showing direction,
                                        lifecycle status, timestamps, item and
                                        variance counts, requester, and
                                        available action.
                                    </caption>
                                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                        <tr>
                                            {sortHeaders.map(
                                                ([sort, label]) => (
                                                    <th
                                                        key={sort}
                                                        scope="col"
                                                        aria-sort={
                                                            filters.sort ===
                                                            sort
                                                                ? filters.direction ===
                                                                  'asc'
                                                                    ? 'ascending'
                                                                    : 'descending'
                                                                : 'none'
                                                        }
                                                        className="px-4 py-3"
                                                    >
                                                        <Link
                                                            href={sortHref(
                                                                sort,
                                                            )}
                                                            preserveScroll
                                                            preserveState
                                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                        >
                                                            {label}
                                                            <SortIndicator
                                                                active={
                                                                    filters.sort ===
                                                                    sort
                                                                }
                                                                direction={
                                                                    filters.direction
                                                                }
                                                            />
                                                        </Link>
                                                    </th>
                                                ),
                                            )}
                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Source → Destination
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Items
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Variance
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                Requested by
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right"
                                            >
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => {
                                            const editable =
                                                canCreate &&
                                                row.status === 'draft';

                                            return (
                                                <tr
                                                    key={row.id}
                                                    className="border-b border-border transition-colors last:border-b-0 hover:bg-muted/30"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        <Link
                                                            href={StockTransferController.edit(
                                                                row.id,
                                                            )}
                                                            className="underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                        >
                                                            {row.number}
                                                        </Link>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <StatusBadge
                                                            label={transferStatusLabel(
                                                                row.status,
                                                            )}
                                                            variant={transferStatusVariant(
                                                                row.status,
                                                            )}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                        {row.requestedAt ? (
                                                            <time
                                                                dateTime={
                                                                    row.requestedAt
                                                                }
                                                            >
                                                                {formatOrganizationDate(
                                                                    row.requestedAt,
                                                                    timezone,
                                                                )}
                                                            </time>
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                        {row.shippedAt ? (
                                                            <time
                                                                dateTime={
                                                                    row.shippedAt
                                                                }
                                                            >
                                                                {formatOrganizationDate(
                                                                    row.shippedAt,
                                                                    timezone,
                                                                )}
                                                            </time>
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                        {row.receivedAt ? (
                                                            <time
                                                                dateTime={
                                                                    row.receivedAt
                                                                }
                                                            >
                                                                {formatOrganizationDate(
                                                                    row.receivedAt,
                                                                    timezone,
                                                                )}
                                                            </time>
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <TransferDirection
                                                            row={row}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 tabular-nums">
                                                        {row.itemCount.toLocaleString()}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <VarianceState
                                                            count={
                                                                row.varianceItemCount
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {row.requestedByName ?? (
                                                            <span className="text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={StockTransferController.edit(
                                                                    row.id,
                                                                )}
                                                            >
                                                                {editable
                                                                    ? 'Edit'
                                                                    : 'View'}
                                                                <span className="sr-only">
                                                                    {' '}
                                                                    {row.number}
                                                                </span>
                                                            </Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                    <div className="flex flex-col gap-3 border-t border-border bg-muted/20 px-4 py-3 text-sm text-muted-foreground sm:flex-row sm:items-start">
                        <Info
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <p>
                            Shipping removes stock from the source location.
                            Destination stock changes only when receipt is
                            recorded, and received quantities and variances
                            remain attached to the transfer audit trail.
                        </p>
                    </div>
                    {pagination.total > 0 && (
                        <>
                            <div className="border-t border-border px-4 py-3">
                                <Field
                                    id="per-page"
                                    label="Rows per page"
                                    className="max-w-40"
                                >
                                    <NativeSelect
                                        value={pagination.perPage}
                                        onChange={(event) =>
                                            router.visit(
                                                hrefFor({
                                                    per_page:
                                                        event.currentTarget
                                                            .value,
                                                    page: null,
                                                }),
                                                {
                                                    preserveScroll: true,
                                                    preserveState: true,
                                                },
                                            )
                                        }
                                    >
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </NativeSelect>
                                </Field>
                            </div>
                            <PaginationControls
                                currentPage={pagination.currentPage}
                                from={pagination.from}
                                to={pagination.to}
                                total={pagination.total}
                                lastPage={pagination.lastPage}
                                previousPageUrl={pagination.previousPageUrl}
                                nextPageUrl={pagination.nextPageUrl}
                                itemLabel="transfers"
                                preserveScroll
                                preserveState
                            />
                        </>
                    )}
                </section>
            </div>
        </>
    );
}
