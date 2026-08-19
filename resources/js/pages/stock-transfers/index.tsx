import { Form, Head, Link, router } from '@inertiajs/react';
import {
    Ban,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    ChevronsLeft,
    ChevronsRight,
    ChevronsUpDown,
    Eye,
    FilePenLine,
    Filter,
    Info,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    TriangleAlert,
    Truck,
} from 'lucide-react';

import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

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

type LocationOption = {
    id: number;
    name: string;
};

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

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

const quickViews: Array<{ label: string; value: StockTransferView }> = [
    { label: 'All', value: 'all' },
    { label: 'Draft', value: 'draft' },
    { label: 'Awaiting receipt', value: 'shipped' },
    { label: 'Received', value: 'received' },
    { label: 'Variance', value: 'variance' },
];

/** Format workflow timestamps consistently in the active organization's timezone. */
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

/** Render one stock-transfer lifecycle status using text, icon, and color. */
function StatusBadge({ status }: { status: StockTransferStatus }) {
    if (status === 'received') {
        return (
            <Badge
                variant="outline"
                className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
            >
                <CheckCircle2 aria-hidden="true" />
                Received
            </Badge>
        );
    }

    if (status === 'shipped') {
        return (
            <Badge
                variant="outline"
                className="border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300"
            >
                <Truck aria-hidden="true" />
                Shipped
            </Badge>
        );
    }

    if (status === 'cancelled') {
        return (
            <Badge
                variant="outline"
                className="border-destructive/30 bg-destructive/10 text-destructive dark:border-destructive/50 dark:bg-destructive/20"
            >
                <Ban aria-hidden="true" />
                Cancelled
            </Badge>
        );
    }

    return (
        <Badge variant="secondary">
            <FilePenLine aria-hidden="true" />
            Draft
        </Badge>
    );
}

/** Show the active sort direction without implying unsupported table sorting. */
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

/** Keep numbered pagination compact while retaining nearby navigation context. */
function paginationPages(currentPage: number, lastPage: number): number[] {
    const visiblePages = 5;
    let start = Math.max(1, currentPage - Math.floor(visiblePages / 2));
    let end = Math.min(lastPage, start + visiblePages - 1);

    start = Math.max(1, end - visiblePages + 1);
    end = Math.min(lastPage, start + visiblePages - 1);

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
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
    const hasFilters =
        filters.search !== null ||
        filters.view !== 'all' ||
        filters.fromLocationId !== null ||
        filters.toLocationId !== null ||
        filters.from !== null ||
        filters.to !== null;

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

    const sortHref = (sort: StockTransferSort): string => {
        const direction =
            filters.sort === sort && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        return hrefFor({
            sort,
            direction,
            page: null,
        });
    };

    const pages = paginationPages(pagination.currentPage, pagination.lastPage);

    return (
        <>
            <Head title="Stock transfers" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Stock transfers
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Move inventory safely between storage locations.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {canViewReport && (
                            <Button variant="outline" asChild>
                                <Link href={StockTransferController.variance()}>
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
                                <Link href={StockTransferController.create()}>
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    New transfer
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

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

                <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                    <Form
                        action={StockTransferController.index().url}
                        method="get"
                    >
                        {({ errors, processing }) => (
                            <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-[1.35fr_0.85fr_1fr_1fr_0.8fr_0.8fr_auto]">
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

                                <div className="grid gap-2">
                                    <Label htmlFor="search">Search</Label>
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="search"
                                            type="search"
                                            name="search"
                                            defaultValue={filters.search ?? ''}
                                            placeholder="Number, location, storage, or requester"
                                            className="pl-9"
                                            autoComplete="off"
                                            aria-invalid={
                                                errors.search ? true : undefined
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="view">Status</Label>
                                    <select
                                        id="view"
                                        name="view"
                                        defaultValue={filters.view}
                                        className={selectClassName}
                                        aria-invalid={
                                            errors.view ? true : undefined
                                        }
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
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="from_location_id">
                                        Source location
                                    </Label>
                                    <select
                                        id="from_location_id"
                                        name="from_location_id"
                                        defaultValue={
                                            filters.fromLocationId?.toString() ??
                                            ''
                                        }
                                        className={selectClassName}
                                        aria-invalid={
                                            errors.from_location_id
                                                ? true
                                                : undefined
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
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="to_location_id">
                                        Destination location
                                    </Label>
                                    <select
                                        id="to_location_id"
                                        name="to_location_id"
                                        defaultValue={
                                            filters.toLocationId?.toString() ??
                                            ''
                                        }
                                        className={selectClassName}
                                        aria-invalid={
                                            errors.to_location_id
                                                ? true
                                                : undefined
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
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="from">Requested from</Label>
                                    <Input
                                        id="from"
                                        type="date"
                                        name="from"
                                        defaultValue={filters.from ?? ''}
                                        aria-invalid={
                                            errors.from ? true : undefined
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="to">Requested to</Label>
                                    <Input
                                        id="to"
                                        type="date"
                                        name="to"
                                        defaultValue={filters.to ?? ''}
                                        aria-invalid={
                                            errors.to ? true : undefined
                                        }
                                    />
                                </div>

                                <div className="flex items-end gap-2 md:col-span-2 xl:col-span-1">
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

                                {Object.keys(errors).length > 0 && (
                                    <div
                                        role="alert"
                                        className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive md:col-span-2 xl:col-span-7"
                                    >
                                        One or more stock-transfer filters are
                                        invalid. Review the values or reset the
                                        filters and try again.
                                    </div>
                                )}
                            </div>
                        )}
                    </Form>

                    <nav
                        aria-label="Stock transfer views"
                        className="flex gap-1 overflow-x-auto border-t border-sidebar-border/70 px-4 pt-2 dark:border-sidebar-border"
                    >
                        {quickViews.map((view) => {
                            const active = filters.view === view.value;

                            return (
                                <Link
                                    key={view.value}
                                    href={hrefFor({
                                        view: view.value,
                                        page: null,
                                    })}
                                    preserveScroll
                                    preserveState
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        active
                                            ? 'border-primary text-foreground'
                                            : 'border-transparent text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {view.label}
                                </Link>
                            );
                        })}
                    </nav>
                </section>

                <section
                    className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border"
                    aria-labelledby="stock-transfers-table-title"
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
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

                        <Badge variant="outline">
                            {pagination.total.toLocaleString()}{' '}
                            {pagination.total === 1 ? 'transfer' : 'transfers'}
                        </Badge>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1640px] text-sm">
                            <caption className="sr-only">
                                Stock transfers showing source and destination,
                                lifecycle status, workflow timestamps, item and
                                variance counts, requester, and available
                                action.
                            </caption>
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        <Link
                                            href={sortHref('number')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Number
                                            <SortIndicator
                                                active={
                                                    filters.sort === 'number'
                                                }
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Source
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Destination
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <Link
                                            href={sortHref('status')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Status
                                            <SortIndicator
                                                active={
                                                    filters.sort === 'status'
                                                }
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <Link
                                            href={sortHref('requested_at')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Requested
                                            <SortIndicator
                                                active={
                                                    filters.sort ===
                                                    'requested_at'
                                                }
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <Link
                                            href={sortHref('shipped_at')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Shipped
                                            <SortIndicator
                                                active={
                                                    filters.sort ===
                                                    'shipped_at'
                                                }
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <Link
                                            href={sortHref('received_at')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Received
                                            <SortIndicator
                                                active={
                                                    filters.sort ===
                                                    'received_at'
                                                }
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Items
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Variance
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Requested by
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-16 px-4 py-3 text-right"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={11}
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
                                                    ? 'No stock transfers match these filters.'
                                                    : 'No stock transfers yet.'}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hasFilters
                                                    ? 'Adjust or reset the filters to see other transfer history.'
                                                    : 'Create a transfer when inventory needs to move between storage locations.'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row) => {
                                        const canEditDraft =
                                            canCreate && row.status === 'draft';

                                        return (
                                            <tr
                                                key={row.id}
                                                className="border-b border-sidebar-border/70 transition-colors last:border-b-0 hover:bg-muted/30 dark:border-sidebar-border"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    <Link
                                                        href={StockTransferController.edit(
                                                            row.id,
                                                        )}
                                                        className="text-blue-600 underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:text-blue-400"
                                                    >
                                                        {row.number}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div>
                                                        {row.fromLocationName}
                                                    </div>
                                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                                        {
                                                            row.fromStorageLocationName
                                                        }
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div>
                                                        {row.toLocationName}
                                                    </div>
                                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                                        {
                                                            row.toStorageLocationName
                                                        }
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        status={row.status}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.requestedAt ===
                                                    null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
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
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.shippedAt === null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
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
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.receivedAt === null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
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
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 tabular-nums">
                                                    {row.itemCount.toLocaleString()}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.varianceItemCount ===
                                                    null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : row.varianceItemCount ===
                                                      0 ? (
                                                        <span className="text-emerald-700 dark:text-emerald-300">
                                                            No variance
                                                        </span>
                                                    ) : (
                                                        <span className="font-medium text-destructive">
                                                            {row.varianceItemCount.toLocaleString()}{' '}
                                                            {row.varianceItemCount ===
                                                            1
                                                                ? 'item'
                                                                : 'items'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.requestedByName ?? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={StockTransferController.edit(
                                                                row.id,
                                                            )}
                                                            aria-label={`${canEditDraft ? 'Edit' : 'View'} ${row.number}`}
                                                        >
                                                            {canEditDraft ? (
                                                                <Pencil
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            ) : (
                                                                <Eye
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            )}
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex items-start gap-2 border-t border-sidebar-border/70 bg-blue-50/60 px-4 py-3 text-sm text-blue-800 dark:border-sidebar-border dark:bg-blue-950/20 dark:text-blue-200">
                        <Info
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <p>
                            Shipping removes stock from the source location.
                            Destination stock changes only when receipt is
                            recorded, and received quantities and variances stay
                            attached to the transfer audit trail.
                        </p>
                    </div>

                    {pagination.total > 0 && (
                        <div className="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 lg:flex-row lg:items-center lg:justify-between dark:border-sidebar-border">
                            <p className="text-sm text-muted-foreground">
                                Showing {pagination.from ?? 0} to{' '}
                                {pagination.to ?? 0} of{' '}
                                {pagination.total.toLocaleString()} results
                            </p>

                            <div className="flex flex-wrap items-center gap-3">
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <span>Rows per page</span>
                                    <select
                                        aria-label="Rows per page"
                                        value={pagination.perPage}
                                        className="h-8 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        onChange={(event) => {
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
                                            );
                                        }}
                                    >
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select>
                                </label>

                                {pagination.lastPage > 1 && (
                                    <nav
                                        className="flex items-center gap-1"
                                        aria-label="Stock transfer pagination"
                                    >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            disabled={
                                                pagination.currentPage === 1
                                            }
                                            asChild={
                                                pagination.currentPage !== 1
                                            }
                                        >
                                            {pagination.currentPage === 1 ? (
                                                <ChevronsLeft
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <Link
                                                    href={hrefFor({ page: 1 })}
                                                    preserveScroll
                                                    preserveState
                                                    aria-label="First page"
                                                >
                                                    <ChevronsLeft
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Link>
                                            )}
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            disabled={
                                                pagination.previousPageUrl ===
                                                null
                                            }
                                            asChild={
                                                pagination.previousPageUrl !==
                                                null
                                            }
                                        >
                                            {pagination.previousPageUrl ===
                                            null ? (
                                                <ChevronLeft
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <Link
                                                    href={
                                                        pagination.previousPageUrl
                                                    }
                                                    preserveScroll
                                                    preserveState
                                                    aria-label="Previous page"
                                                >
                                                    <ChevronLeft
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Link>
                                            )}
                                        </Button>

                                        {pages.map((page) => (
                                            <Button
                                                key={page}
                                                variant={
                                                    page ===
                                                    pagination.currentPage
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={hrefFor({ page })}
                                                    preserveScroll
                                                    preserveState
                                                    aria-label={`Page ${page}`}
                                                    aria-current={
                                                        page ===
                                                        pagination.currentPage
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                >
                                                    {page}
                                                </Link>
                                            </Button>
                                        ))}

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            disabled={
                                                pagination.nextPageUrl === null
                                            }
                                            asChild={
                                                pagination.nextPageUrl !== null
                                            }
                                        >
                                            {pagination.nextPageUrl === null ? (
                                                <ChevronRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <Link
                                                    href={
                                                        pagination.nextPageUrl
                                                    }
                                                    preserveScroll
                                                    preserveState
                                                    aria-label="Next page"
                                                >
                                                    <ChevronRight
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Link>
                                            )}
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            disabled={
                                                pagination.currentPage ===
                                                pagination.lastPage
                                            }
                                            asChild={
                                                pagination.currentPage !==
                                                pagination.lastPage
                                            }
                                        >
                                            {pagination.currentPage ===
                                            pagination.lastPage ? (
                                                <ChevronsRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <Link
                                                    href={hrefFor({
                                                        page: pagination.lastPage,
                                                    })}
                                                    preserveScroll
                                                    preserveState
                                                    aria-label="Last page"
                                                >
                                                    <ChevronsRight
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                </Link>
                                            )}
                                        </Button>
                                    </nav>
                                )}
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

StockTransferIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock transfers',
            href: StockTransferController.index(),
        },
    ],
};
