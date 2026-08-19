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
    ClipboardList,
    Clock3,
    Eye,
    FilePenLine,
    Filter,
    Info,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Send,
    TriangleAlert,
} from 'lucide-react';

import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import { DashboardMetricCard } from '@/components/dashboard/dashboard-metric-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type StockCountStatus = 'draft' | 'submitted' | 'finalized' | 'cancelled';
type StockCountView =
    | 'all'
    | 'open'
    | 'draft'
    | 'submitted'
    | 'finalized'
    | 'cancelled'
    | 'variance';
type StockCountSort =
    'latest' | 'number' | 'status' | 'counted_at' | 'finalized_at';
type SortDirection = 'asc' | 'desc';

type StockCountRow = {
    id: number;
    number: string;
    status: StockCountStatus;
    locationName: string;
    storageLocationName: string;
    countedByName: string | null;
    countedAt: string | null;
    finalizedAt: string | null;
    varianceItemCount: number | null;
};

type StockCountSummary = {
    totalCount: number;
    openCount: number;
    finalizedTodayCount: number;
    varianceAlertCount: number;
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

type StorageLocationOption = {
    id: number;
    locationId: number;
    name: string;
};

type Filters = {
    search: string | null;
    view: StockCountView;
    locationId: number | null;
    storageLocationId: number | null;
    from: string | null;
    to: string | null;
    sort: StockCountSort;
    direction: SortDirection;
    perPage: number;
};

type Props = {
    rows: StockCountRow[];
    pagination: Pagination;
    summary: StockCountSummary;
    locationOptions: LocationOption[];
    storageLocationOptions: StorageLocationOption[];
    filters: Filters;
    timezone: string;
    canCreate: boolean;
    canViewReport: boolean;
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-[3px] aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50';

const quickViews: Array<{ label: string; value: StockCountView }> = [
    { label: 'All', value: 'all' },
    { label: 'Open', value: 'open' },
    { label: 'Finalized', value: 'finalized' },
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

/** Render one stock-count lifecycle status using text, icon, and color. */
function StatusBadge({ status }: { status: StockCountStatus }) {
    if (status === 'finalized') {
        return (
            <Badge
                variant="outline"
                className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
            >
                <CheckCircle2 aria-hidden="true" />
                Finalized
            </Badge>
        );
    }

    if (status === 'submitted') {
        return (
            <Badge
                variant="outline"
                className="border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300"
            >
                <Send aria-hidden="true" />
                Submitted
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

/** Render the server-authoritative Stock Counts operational index. */
export default function StockCountIndex({
    rows,
    pagination,
    summary,
    locationOptions,
    storageLocationOptions,
    filters,
    timezone,
    canCreate,
    canViewReport,
}: Props) {
    const hasFilters =
        filters.search !== null ||
        filters.view !== 'all' ||
        filters.locationId !== null ||
        filters.storageLocationId !== null ||
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

        if (filters.locationId !== null) {
            params.set('location_id', filters.locationId.toString());
        }

        if (filters.storageLocationId !== null) {
            params.set(
                'storage_location_id',
                filters.storageLocationId.toString(),
            );
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

        return `${StockCountController.index().url}?${params.toString()}`;
    };

    const sortHref = (sort: StockCountSort): string => {
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
            <Head title="Stock counts" />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Stock counts
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Reconcile physical stock with the inventory ledger.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {canViewReport && (
                            <Button variant="outline" asChild>
                                <Link href={StockCountController.variance()}>
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
                                <Link href={StockCountController.create()}>
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    New stock count
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardMetricCard
                        title="Total counts"
                        value={summary.totalCount.toLocaleString()}
                        description="All stock-count workflows"
                        icon={ClipboardList}
                        tone="blue"
                    />

                    <DashboardMetricCard
                        title="Open counts"
                        value={summary.openCount.toLocaleString()}
                        description="Draft or submitted counts"
                        icon={Clock3}
                        tone="amber"
                    />

                    <DashboardMetricCard
                        title="Finalized today"
                        value={summary.finalizedTodayCount.toLocaleString()}
                        description={`Today in ${timezone}`}
                        icon={CheckCircle2}
                        tone="emerald"
                    />

                    <DashboardMetricCard
                        title="Variance alerts"
                        value={summary.varianceAlertCount.toLocaleString()}
                        description="Finalized counts with persisted variance"
                        icon={TriangleAlert}
                        tone="amber"
                    />
                </div>

                <section className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm dark:border-sidebar-border">
                    <Form
                        action={StockCountController.index().url}
                        method="get"
                    >
                        {({ errors, processing }) => (
                            <div className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-[1.45fr_0.9fr_0.95fr_0.95fr_0.8fr_0.8fr_auto]">
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
                                            placeholder="Number, location, or storage"
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
                                        <option value="open">
                                            Open (draft + submitted)
                                        </option>
                                        <option value="draft">Draft</option>
                                        <option value="submitted">
                                            Submitted
                                        </option>
                                        <option value="finalized">
                                            Finalized
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
                                    <Label htmlFor="location_id">
                                        Location
                                    </Label>
                                    <select
                                        id="location_id"
                                        name="location_id"
                                        defaultValue={
                                            filters.locationId?.toString() ?? ''
                                        }
                                        className={selectClassName}
                                        aria-invalid={
                                            errors.location_id
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
                                    <Label htmlFor="storage_location_id">
                                        Storage
                                    </Label>
                                    <select
                                        id="storage_location_id"
                                        name="storage_location_id"
                                        defaultValue={
                                            filters.storageLocationId?.toString() ??
                                            ''
                                        }
                                        className={selectClassName}
                                        aria-invalid={
                                            errors.storage_location_id
                                                ? true
                                                : undefined
                                        }
                                    >
                                        <option value="">All storage</option>
                                        {storageLocationOptions.map(
                                            (storage) => (
                                                <option
                                                    key={storage.id}
                                                    value={storage.id}
                                                >
                                                    {storage.name}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="from">Counted from</Label>
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
                                    <Label htmlFor="to">Counted to</Label>
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
                                            href={StockCountController.index()}
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
                                        One or more stock-count filters are
                                        invalid. Review the values or reset the
                                        filters and try again.
                                    </div>
                                )}
                            </div>
                        )}
                    </Form>

                    <nav
                        aria-label="Stock count views"
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
                    aria-labelledby="stock-counts-table-title"
                >
                    <div className="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border">
                        <div>
                            <h2
                                id="stock-counts-table-title"
                                className="text-sm font-semibold"
                            >
                                Stock count history
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Physical-count workflow evidence for the active
                                organization.
                            </p>
                        </div>

                        <Badge variant="outline">
                            {pagination.total.toLocaleString()}{' '}
                            {pagination.total === 1 ? 'count' : 'counts'}
                        </Badge>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1260px] text-sm">
                            <caption className="sr-only">
                                Stock counts showing location, storage, status,
                                actors, timestamps, and persisted variance
                                evidence.
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
                                        Location
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Storage
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
                                        Counted by
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <Link
                                            href={sortHref('counted_at')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Counted
                                            <SortIndicator
                                                active={
                                                    filters.sort ===
                                                    'counted_at'
                                                }
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <Link
                                            href={sortHref('finalized_at')}
                                            preserveScroll
                                            preserveState
                                            className="inline-flex items-center gap-1.5 font-medium text-foreground underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            Finalized
                                            <SortIndicator
                                                active={
                                                    filters.sort ===
                                                    'finalized_at'
                                                }
                                                direction={filters.direction}
                                            />
                                        </Link>
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Variance
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
                                            colSpan={9}
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
                                                    ? 'No stock counts match these filters.'
                                                    : 'No stock counts yet.'}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hasFilters
                                                    ? 'Adjust or reset the filters to see other stock-count history.'
                                                    : 'Create a stock count when you are ready to reconcile physical inventory.'}
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
                                                        href={StockCountController.edit(
                                                            row.id,
                                                        )}
                                                        className="text-blue-600 underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:text-blue-400"
                                                    >
                                                        {row.number}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.locationName}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.storageLocationName}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        status={row.status}
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    {row.countedByName ?? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.countedAt === null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
                                                        <time
                                                            dateTime={
                                                                row.countedAt
                                                            }
                                                        >
                                                            {formatOrganizationDate(
                                                                row.countedAt,
                                                                timezone,
                                                            )}
                                                        </time>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                                    {row.finalizedAt ===
                                                    null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
                                                        <time
                                                            dateTime={
                                                                row.finalizedAt
                                                            }
                                                        >
                                                            {formatOrganizationDate(
                                                                row.finalizedAt,
                                                                timezone,
                                                            )}
                                                        </time>
                                                    )}
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
                                                            0 items
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
                                                <td className="px-4 py-2 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={StockCountController.edit(
                                                                row.id,
                                                            )}
                                                            aria-label={`${
                                                                canEditDraft
                                                                    ? 'Edit'
                                                                    : 'View'
                                                            } ${row.number}`}
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
                            Finalized counts are locked for audit integrity and
                            cannot be edited. Record later corrections through a
                            new stock count or the appropriate inventory
                            adjustment workflow.
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
                                        aria-label="Stock count pagination"
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

StockCountIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Stock counts',
            href: StockCountController.index(),
        },
    ],
};
